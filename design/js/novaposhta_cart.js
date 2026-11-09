/*
 * Вибір пункту видачі Нової Пошти в кошику. Усе, що приходить із довідника, потрапляє на
 * сторінку лише через textContent: назви — сторонні дані.
 */
(function () {
    'use strict';

    var DEBOUNCE_MS = 200;

    function route(name) {
        // Теми оголошують `okay` через let/const: він глобальний, але не є властивістю window.
        return typeof okay !== 'undefined' && okay.router ? okay.router[name] : null;
    }

    function parse(json) {
        try {
            return json ? JSON.parse(json) : null;
        } catch (e) {
            return null;
        }
    }

    function format(template, values) {
        return String(template || '').replace(/%(\w+)%/g, function (m, key) {
            return values[key] !== undefined ? values[key] : m;
        });
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    }

    /** Теми на основі okay_shop піднімають label поля класом `filled`, який ставлять лише на blur. */
    /** Поле містить лише назву: тип, район і область — у рядку під ним. */
    function cityText(settlement) {
        return settlement.title || settlement.name;
    }

    function syncFilled(input) {
        if (input.parentNode) {
            input.parentNode.classList.toggle('filled', input.value.trim() !== '');
        }
    }

    function Block(root) {
        this.root = root;
        root.npBlock = this;
        this.pointCheck = root.querySelector('.np-cart__point-check');
        this.texts = parse(root.getAttribute('data-texts')) || {};
        this.chip = root.getAttribute('data-chip');
        this.chipSelected = root.getAttribute('data-chip-selected');
        this.city = root.querySelector('.np-cart__city-input');
        this.popular = root.querySelector('.np-cart__popular');
        this.cityBox = root.querySelector('.np-cart__city');
        this.cityLine = root.querySelector('.np-cart__city-line');
        this.chosen = root.querySelector('.np-cart__chosen');
        this.step = 'city';
        this.codPayments = (root.getAttribute('data-cod-payments') || '').split(',').filter(Boolean);
        this.priceHidden = root.getAttribute('data-price-hidden') === '1';
        this.codFeeText = null;
        this.suggest = root.querySelector('.np-cart__suggest');
        this.points = root.querySelector('.np-cart__points');
        this.groups = root.querySelector('.np-cart__groups');
        this.searchBox = root.querySelector('.np-cart__search');
        this.search = root.querySelector('.np-cart__search-input');
        this.list = root.querySelector('.np-cart__list');
        this.more = root.querySelector('.np-cart__more');
        this.message = root.querySelector('.np-cart__message');
        this.choice = root.querySelector('.np-cart__choice');
        this.quoteBox = root.querySelector('.np-cart__quote');
        this.deliveryId = root.getAttribute('data-delivery-id');
        this.mode = root.getAttribute('data-mode') === 'address' ? 'address' : 'points';
        this.address = root.querySelector('.np-cart__address');
        this.street = root.querySelector('.np-cart__street-input');
        this.streetSuggest = root.querySelector('.np-cart__street-suggest');
        this.streetHint = root.querySelector('.np-cart__street-hint');
        this.streetRef = root.querySelector('.np-cart__street-ref');
        this.house = root.querySelector('.np-cart__house-input');
        this.flat = root.querySelector('.np-cart__flat-input');
        this.floor = root.querySelector('.np-cart__floor-input');
        this.elevator = root.querySelector('.np-cart__elevator');
        this.note = root.querySelector('.np-cart__note-input');
        this.noteCount = root.querySelector('.np-cart__note-count');
        this.lifting = root.querySelector('.np-cart__lifting');
        this.liftingInput = root.querySelector('.np-cart__lifting-input');
        this.streetRequest = null;
        this.streetTimer = null;
        this.quoteTimer = null;
        this.quoteRequest = null;
        this.themePrice = null;
        this.settlementInput = root.querySelector('.np-cart__settlement-id');
        this.divisionInput = root.querySelector('.np-cart__division-id');
        this.settlement = parse(root.getAttribute('data-settlement'));
        this.division = parse(root.getAttribute('data-division'));
        this.groupId = null;
        this.suggestions = [];
        this.active = -1;
        this.cityRequest = null;
        this.pointsRequest = null;
        this.cityTimer = null;
        this.searchTimer = null;
        this.bind();
        this.showUnpricedSummary();
        this.showCodLines();
        if (this.mode === 'address') {
            this.bindAddress();
        }
        if (this.settlement) {
            if (this.address) {
                this.address.hidden = false;
            }
            this.markPopular();
            this.showChoice();
            // Вибір, відновлений із сесії або останнього замовлення, показується як вибраний, а не списком.
            if (this.mode === 'points' && this.division) {
                this.setStep('chosen');
            } else {
                this.setStep('points');
                this.load();
            }
            this.requestQuote();
        } else {
            this.setStep('city');
        }
    }

    /**
     * city: популярні міста й поле міста; points: місто одним рядком, групи, пошук, список
     * (режим адреси: форма адреси); chosen: лише вибраний пункт видачі.
     */
    Block.prototype.setStep = function (step) {
        this.step = step;
        this.root.setAttribute('data-step', step);
        if (this.popular) {
            this.popular.hidden = step !== 'city';
        }
        this.cityBox.hidden = step !== 'city';
        this.cityLine.hidden = step !== 'points';
        if (step === 'points' && this.settlement) {
            // Тип звичайним шрифтом перед жирною назвою; район і область розрізняють тезок.
            this.cityLine.querySelector('.np-cart__city-line-type').textContent = this.settlement.type || '';
            this.cityLine.querySelector('.np-cart__city-line-name').textContent = this.settlement.title || this.settlement.name;
            this.cityLine.querySelector('.np-cart__city-line-area').textContent = [this.settlement.district, this.settlement.area].filter(Boolean).join(', ');
        }
        this.chosen.hidden = step !== 'chosen';
        if (step === 'chosen') {
            this.showChosen();
        }
        if (step !== 'points') {
            this.points.hidden = true;
        }
    };

    /**
     * Рядок комісії кожної оплати "післяплата": Нова Пошта бере її у відділенні, окремо від суми.
     * Модуль ставить рядок у блок опису оплати (front_cart_payment); тема без цього блоку отримує
     * його всередині label, бо теми показують опис лише одразу після label.
     */
    Block.prototype.showCodLines = function () {
        var self = this;
        var input = this.themeInput();
        var mine = input && input.length && input.is(':checked');
        // Два способи доставки можуть ділити одну оплату з післяплатою: спосіб ховає лише рядок, який заповнив сам.
        document.querySelectorAll('.np-cod-line[data-np-delivery="' + this.deliveryId + '"]').forEach(function (line) {
            if (!mine) {
                if (line.hasAttribute('data-np-cod-payment')) {
                    line.hidden = true;
                    line.removeAttribute('data-np-delivery');
                } else {
                    line.remove();
                }
            }
        });
        if (!mine) {
            return;
        }
        var text = this.codFeeText && !this.priceHidden
            ? format(this.texts.cart_cod_fee, { fee: this.codFeeText })
            : this.texts.cart_cod_fee_unknown || '';
        this.codPayments.forEach(function (id) {
            id = id.replace(/[^0-9]/g, '');
            var line = document.querySelector('.np-cod-line[data-np-cod-payment="' + id + '"]');
            if (!line) {
                var payment = document.getElementById('payment_' + id);
                var label = payment ? (payment.closest('label') || document.querySelector('label[for="' + payment.id + '"]')) : null;
                if (!label) {
                    return;
                }
                var host = label.querySelector('.vs-option-card__body') || label.querySelector('.delivery__name') || label;
                line = host.querySelector('.np-cod-line[data-np-delivery="' + self.deliveryId + '"]');
                if (!line) {
                    line = el('span', 'np-cod-line');
                    host.appendChild(line);
                }
            }
            line.setAttribute('data-np-delivery', self.deliveryId);
            line.textContent = text;
            line.hidden = false;
        });
    };

    /** Прибирає повідомлення, яке показав валідатор теми, щойно поле стає правильним. */
    Block.prototype.revalidate = function (element) {
        var $ = window.jQuery;
        if (element && $ && $(element).hasClass('error') && $(element.form).data('validator')) {
            $(element).valid();
        }
    };

    Block.prototype.showChosen = function () {
        var cityBox = this.chosen.querySelector('.np-cart__chosen-city');
        cityBox.textContent = '';
        if (this.settlement) {
            if (this.settlement.type) {
                cityBox.appendChild(el('span', 'np-cart__city-line-type', this.settlement.type + ' '));
            }
            cityBox.appendChild(el('b', null, this.settlement.title || this.settlement.name));
            var where = [this.settlement.district, this.settlement.area].filter(Boolean).join(', ');
            if (where) {
                cityBox.appendChild(document.createTextNode(', ' + where));
            }
        }
        this.chosen.querySelector('.np-cart__chosen-title').textContent = this.division ? this.division.title || '' : '';
        var place = this.chosen.querySelector('.np-cart__chosen-place');
        place.textContent = this.division && this.division.place ? this.division.place : '';
        place.hidden = place.textContent === '';
        var schedule = this.chosen.querySelector('.np-cart__chosen-schedule');
        schedule.textContent = '';
        var hours = this.division ? this.scheduleNode(this.division.schedule) : null;
        if (hours) {
            var around = String(this.texts.cart_schedule || '%schedule%').split('%schedule%');
            schedule.appendChild(document.createTextNode(around[0]));
            schedule.appendChild(hours);
            schedule.appendChild(document.createTextNode(around[1] || ''));
        }
        schedule.hidden = !hours;
    };

    /**
     * Тиждень із довідника як "Пн–Пт 08:00–21:00, Сб–Нд 08:00–19:00": групи
     * [перший день, останній день, години] (0 = понеділок, null години = вихідний) або true — цілодобово.
     * Кожна група лишається в одному рядку.
     */
    Block.prototype.scheduleNode = function (schedule) {
        if (schedule === true) {
            // Такий самий вигляд, як у годин роботи: дрібно й приглушено.
            return el('span', 'np-cart__schedule np-cart__day', this.texts.cart_all_day || '');
        }
        if (!Array.isArray(schedule) || schedule.length === 0) {
            return null;
        }
        var days = String(this.texts.cart_weekdays || '').split(',');
        var self = this;
        var line = el('span', 'np-cart__schedule');
        schedule.forEach(function (group, index) {
            var span = days[group[0]] + (group[1] !== group[0] ? '–' + days[group[1]] : '');
            if (index > 0) {
                line.appendChild(document.createTextNode(', '));
            }
            line.appendChild(el('span', 'np-cart__day', span + ' ' + (group[2] || self.texts.cart_day_off || '')));
        });
        return line;
    };

    Block.prototype.bind = function () {
        var self = this;
        this.root.querySelectorAll('[data-np-city]').forEach(function (button) {
            button.addEventListener('click', function () {
                self.chooseSettlement({ id: Number(button.getAttribute('data-np-city')), name: button.getAttribute('data-np-city-name'), title: button.getAttribute('data-np-city-name'), type: button.getAttribute('data-np-city-type') || '' });
            });
        });
        this.city.addEventListener('input', function () {
            if (self.settlement && self.city.value.trim() !== cityText(self.settlement)) {
                self.dropSettlement();
            }
            clearTimeout(self.cityTimer);
            self.cityTimer = setTimeout(function () { self.findCities(); }, DEBOUNCE_MS);
        });
        this.city.addEventListener('keydown', function (event) { self.cityKeys(event); });
        this.root.querySelector('.np-cart__change-city').addEventListener('click', function () {
            self.city.value = '';
            syncFilled(self.city);
            self.dropSettlement();
            self.city.focus();
        });
        this.root.querySelector('.np-cart__change').addEventListener('click', function () {
            self.setStep('points');
            self.load();
            if (self.search && !self.searchBox.hidden) {
                self.search.focus();
            }
        });
        this.city.addEventListener('blur', function () { setTimeout(function () { self.closeSuggest(); }, 150); });
        if (!this.search) {
            return;
        }
        this.search.addEventListener('input', function () {
            clearTimeout(self.searchTimer);
            self.searchTimer = setTimeout(function () { self.load(); }, DEBOUNCE_MS);
        });
        this.search.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    };

    Block.prototype.findCities = function () {
        var self = this;
        var query = this.city.value.trim();
        if (query === '' || !route('Sviat_NovaPoshta_cart_settlements')) {
            this.closeSuggest();
            return;
        }
        if (this.cityRequest) {
            this.cityRequest.abort();
        }
        this.cityRequest = new AbortController();
        fetch(route('Sviat_NovaPoshta_cart_settlements') + '?q=' + encodeURIComponent(query) + (this.mode === 'address' ? '&mode=address' : ''), { credentials: 'same-origin', signal: this.cityRequest.signal })
            .then(function (response) { return response.json(); })
            .then(function (data) { self.showSuggest(data.settlements || []); })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    self.say(self.texts.cart_search_failed);
                }
            });
    };

    Block.prototype.showSuggest = function (items) {
        var self = this;
        this.suggestions = items;
        this.active = -1;
        this.suggest.textContent = '';
        if (items.length === 0) {
            var none = el('li', 'np-cart__suggest-empty', this.texts.cart_no_city);
            none.setAttribute('aria-disabled', 'true');
            this.suggest.appendChild(none);
        }
        items.forEach(function (item, index) {
            var option = el('li', 'np-cart__suggest-item');
            option.id = self.suggest.id + '_' + index;
            option.setAttribute('role', 'option');
            if (item.type) {
                option.appendChild(el('span', 'np-cart__suggest-type', item.type + ' '));
            }
            option.appendChild(el('b', null, item.title || item.name));
            var region = [item.district, item.area].filter(Boolean).join(', ');
            if (region) {
                option.appendChild(el('span', 'np-cart__suggest-region', region));
            }
            option.addEventListener('mousedown', function (event) {
                event.preventDefault();
                self.chooseSettlement(item);
            });
            self.suggest.appendChild(option);
        });
        this.suggest.hidden = false;
        this.city.setAttribute('aria-expanded', 'true');
    };

    Block.prototype.closeSuggest = function () {
        this.suggest.hidden = true;
        this.city.setAttribute('aria-expanded', 'false');
        this.city.removeAttribute('aria-activedescendant');
    };

    Block.prototype.cityKeys = function (event) {
        var options = this.suggest.querySelectorAll('.np-cart__suggest-item');
        if (this.suggest.hidden || options.length === 0) {
            return;
        }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            this.active = (this.active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach(function (option, index) {
                option.setAttribute('aria-selected', index === this.active ? 'true' : 'false');
            }, this);
            this.city.setAttribute('aria-activedescendant', options[this.active].id);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            this.chooseSettlement(this.suggestions[this.active >= 0 ? this.active : 0]);
        } else if (event.key === 'Escape') {
            this.closeSuggest();
        }
    };

    Block.prototype.chooseSettlement = function (settlement) {
        if (!settlement) {
            return;
        }
        var changed = !this.settlement || this.settlement.id !== settlement.id;
        this.settlement = settlement;
        this.city.value = cityText(settlement);
        syncFilled(this.city);
        this.settlementInput.value = settlement.id;
        this.closeSuggest();
        this.markPopular();
        if (changed) {
            this.division = null;
            this.divisionInput.value = '';
            this.clearQuote();
            this.groupId = null;
            if (this.search) {
                this.search.value = '';
                syncFilled(this.search);
            }
            if (this.street) {
                // Вулиці належать одному населеному пункту: вулиця попереднього не повинна переходити далі.
                this.street.value = '';
                this.streetRef.value = '';
                syncFilled(this.street);
                this.stopStreets();
            }
        }
        if (this.address) {
            this.address.hidden = false;
        }
        this.showChoice();
        // До того, як поле сховано: jQuery Validate не ховає повідомлення прихованого поля.
        this.revalidate(this.city);
        this.setStep('points');
        this.load();
        if (this.mode === 'address') {
            this.requestQuote();
        }
    };

    /** Змінений текст міста вже не називає вибраний населений пункт: замовлення не повинне тихо піти туди. */
    Block.prototype.dropSettlement = function () {
        if (this.pointsRequest) {
            this.pointsRequest.abort();
        }
        if (this.street) {
            this.stopStreets();
        }
        this.settlement = null;
        this.division = null;
        this.settlementInput.value = '';
        this.divisionInput.value = '';
        this.groupId = null;
        this.points.hidden = true;
        if (this.address) {
            this.address.hidden = true;
        }
        this.say('');
        this.markPopular();
        this.showChoice();
        this.clearQuote();
        this.setStep('city');
    };

    Block.prototype.markPopular = function () {
        var self = this;
        this.root.querySelectorAll('[data-np-city]').forEach(function (button) {
            var on = self.settlement && Number(button.getAttribute('data-np-city')) === self.settlement.id;
            button.classList.toggle(self.chipSelected, !!on);
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    };

    Block.prototype.load = function () {
        var self = this;
        if (this.mode === 'address' || !this.settlement || !route('Sviat_NovaPoshta_cart_divisions')) {
            return;
        }
        if (this.pointsRequest) {
            this.pointsRequest.abort();
        }
        this.pointsRequest = new AbortController();
        var url = route('Sviat_NovaPoshta_cart_divisions') + '?settlement_id=' + encodeURIComponent(this.settlement.id)
            + (this.groupId ? '&group_id=' + encodeURIComponent(this.groupId) : '')
            + '&q=' + encodeURIComponent(this.search.value.trim());
        this.root.classList.add('np-cart--loading');
        fetch(url, { credentials: 'same-origin', signal: this.pointsRequest.signal })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                self.root.classList.remove('np-cart--loading');
                self.render(data);
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    self.root.classList.remove('np-cart--loading');
                    self.say(self.texts.cart_search_failed);
                }
            });
    };

    Block.prototype.render = function (data) {
        var self = this;
        this.say('');
        if (data.dictionary === 'empty') {
            this.points.hidden = true;
            this.say(this.texts.cart_dictionary_empty);
            return;
        }
        if (!data.groups || data.groups.length === 0) {
            this.points.hidden = true;
            this.say(this.texts.cart_no_points);
            return;
        }
        // Список, що приходить, поки показано вибраний пункт видачі, лишається прихованим до "Змінити".
        this.points.hidden = this.step !== 'points';
        this.groupId = data.group;

        this.groups.textContent = '';
        data.groups.forEach(function (group) {
            var on = group.id === data.group;
            var button = el('button', self.chip + (on ? ' ' + self.chipSelected : ''));
            button.type = 'button';
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
            button.appendChild(el('span', null, group.name));
            button.appendChild(el('span', 'np-cart__count', String(group.count)));
            button.addEventListener('click', function () {
                if (self.groupId !== group.id) {
                    self.groupId = group.id;
                    self.search.value = '';
                    syncFilled(self.search);
                    self.load();
                }
            });
            self.groups.appendChild(button);
        });

        var groupTotal = (data.groups.filter(function (g) { return g.id === data.group; })[0] || {}).count || 0;
        this.searchBox.hidden = groupTotal <= 8 && this.search.value.trim() === '';

        this.list.textContent = '';
        if (data.divisions.length === 0) {
            this.list.appendChild(el('p', 'np-cart__empty', this.texts.cart_no_results));
        }
        if (data.divisions.length > 0 && data.divisions.every(function (point) { return (point.problems || []).length > 0; })) {
            this.list.appendChild(el('p', 'np-cart__empty', this.texts.cart_none_fit));
        }
        data.divisions.forEach(function (point) { self.list.appendChild(self.option(point)); });

        this.more.hidden = data.total <= data.divisions.length;
        this.more.textContent = format(this.texts.cart_shown_of, { shown: data.divisions.length, total: data.total });
    };

    Block.prototype.option = function (point) {
        var self = this;
        var unfit = !!(point.problems && point.problems.length);
        var selected = !!(this.division && this.division.id === point.id);
        var option = el('button', 'np-cart__option' + (selected ? ' np-cart__option--selected' : '') + (unfit ? ' np-cart__option--unfit' : ''));
        option.type = 'button';
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', selected ? 'true' : 'false');
        if (unfit) {
            option.setAttribute('aria-disabled', 'true');
        }

        var head = el('span', 'np-cart__option-head');
        head.appendChild(el('b', 'np-cart__option-title', point.title));
        if (point.maxWeight) {
            head.appendChild(el('span', 'np-cart__option-limit', format(this.texts.cart_up_to_kg, { kg: point.maxWeight })));
        }
        option.appendChild(head);
        if (point.place) {
            option.appendChild(el('span', 'np-cart__option-place', point.place));
        }
        var notes = [];
        var lead = null;
        (point.problems || []).forEach(function (problem) {
            var text = self.texts['cart_' + problem] || '';
            var prefix = (text.match(/^[^:]*:\s*/) || [''])[0];
            // "Не вміщається: …" один раз: причина з тим самим початком продовжує попередню.
            notes.push(lead !== null && prefix !== '' && prefix === lead ? text.slice(prefix.length) : text);
            lead = prefix;
        });
        if (notes.length) {
            option.appendChild(el('span', 'np-cart__option-note', notes.join('; ')));
        }
        var hours = unfit ? null : this.scheduleNode(point.schedule);
        if (hours) {
            hours.classList.add('np-cart__option-schedule');
            option.appendChild(hours);
        }

        option.addEventListener('click', function () {
            if (unfit) {
                return;
            }
            self.division = { id: point.id, title: point.title, place: point.place || null, schedule: point.schedule || null };
            self.divisionInput.value = point.id;
            self.list.querySelectorAll('.np-cart__option').forEach(function (other) {
                other.classList.remove('np-cart__option--selected');
                other.setAttribute('aria-selected', 'false');
            });
            option.classList.add('np-cart__option--selected');
            option.setAttribute('aria-selected', 'true');
            self.revalidate(self.pointCheck);
            self.setStep('chosen');
            self.requestQuote();
        });
        return option;
    };

    Block.prototype.showChoice = function () {
        if (this.mode === 'address') {
            var street = this.street ? this.street.value.trim() : '';
            var house = this.house ? this.house.value.trim() : '';
            var flat = this.flat ? this.flat.value.trim() : '';
            if (this.settlement && street && house) {
                var place = [(this.settlement.type ? this.settlement.type + ' ' : '') + (this.settlement.title || this.settlement.name), street, house].join(', ') + (flat ? ', ' + (this.texts.cart_flat_short || '') + ' ' + flat : '');
                this.choice.textContent = format(this.texts.cart_address_choice, { place: place });
                this.choice.hidden = false;
            } else {
                this.choice.hidden = true;
            }
            return;
        }
        // Пункти видачі: картка вибраного пункту говорить усе.
        this.choice.hidden = true;
    };

    /** Власна радіокнопка теми для цього способу доставки (jQuery: теми зберігають ціни в jQuery data). */
    Block.prototype.themeInput = function () {
        var $ = window.jQuery;
        return $ ? $('input[name="delivery_id"][value="' + String(this.deliveryId).replace(/[^0-9]/g, '') + '"]') : null;
    };

    /** Запам'ятовує, що тема показувала до втручання модуля, щоб пізніше це повернути. */
    Block.prototype.captureTheme = function () {
        var input = this.themeInput();
        if (this.themePrice === null && input && input.length) {
            this.themePrice = {
                price: input.data('delivery_price'),
                total: input.data('total_price'),
                free: input.data('is_free_delivery'),
                text: input.closest('.fn_delivery_item').find('.fn_delivery_price').first().text()
            };
        }
    };

    Block.prototype.restoreTheme = function () {
        if (this.themePrice) {
            var original = this.themePrice;
            this.setThemePrice(original.price, original.total, original.text, original.free);
        }
    };

    /**
     * Ще не нараховано (НП не дала ціни або не вибрано пункт видачі чи адресу): підсумок замовлення
     * повторює текст способу доставки замість "0" теми.
     */
    Block.prototype.showUnpricedSummary = function () {
        var input = this.themeInput();
        var texts = [this.texts.cart_price_by_manager, this.texts.cart_price_after_point, this.texts.cart_price_after_address].filter(Boolean);
        if (!input || !input.length || !input.is(':checked') || input.data('is_free_delivery') || Number(input.data('delivery_price')) !== 0) {
            return;
        }
        var shown = input.closest('.fn_delivery_item').find('.fn_delivery_price').first().text().trim().replace(/^\(|\)$/g, '');
        if (texts.indexOf(shown) !== -1) {
            window.jQuery('#fn_total_delivery_price').text(shown);
        }
    };

    /** Пише ціну туди, де її читає тема, потім дає темі перерахувати підсумки. */
    Block.prototype.setThemePrice = function (price, total, text, free) {
        var input = this.themeInput();
        if (!input || !input.length) {
            return;
        }
        this.captureTheme();
        input.data('delivery_price', price).data('total_price', total).data('is_free_delivery', free ? 1 : 0);
        input.closest('.fn_delivery_item').find('.fn_delivery_price').text(text);
        if (input.is(':checked') && typeof okay !== 'undefined' && typeof okay.update_cart_total_price === 'function') {
            okay.update_cart_total_price();
            // Тема заповнює суми "до оплати" лише при зміні способу доставки.
            var $ = window.jQuery;
            $(typeof okay.convert === 'function' ? '.fn_payment_method__item' : []).each(function () {
                var currency = $(this).find('input[name="payment_method_id"]').data('currency_id');
                $(this).find('.fn_payment_price').text(okay.convert(total, currency));
            });
        }
    };

    Block.prototype.clearQuote = function () {
        if (this.quoteRequest) {
            this.quoteRequest.abort();
            this.quoteRequest = null;
        }
        this.quoteBox.hidden = true;
        this.quoteBox.textContent = '';
        this.codFeeText = null;
        this.showCodLines();
        this.restoreTheme();
    };

    Block.prototype.paymentId = function () {
        var checked = document.querySelector('input[name="payment_method_id"]:checked');
        return checked ? checked.value : '';
    };

    Block.prototype.requestQuote = function () {
        var self = this;
        var url = route('Sviat_NovaPoshta_cart_quote');
        var target = this.mode === 'address'
            ? (this.settlement ? '&mode=address&settlement_id=' + encodeURIComponent(this.settlement.id)
                + '&lifting=' + (this.liftingInput && this.liftingInput.checked ? 1 : 0)
                + '&floor=' + encodeURIComponent(this.floor ? this.floor.value.trim() : '')
                + '&elevator=' + (this.elevator && this.elevator.checked ? 1 : 0) : null)
            : (this.division ? '&division_id=' + encodeURIComponent(this.division.id) : null);
        if (!target || !url) {
            return;
        }
        if (this.quoteRequest) {
            this.quoteRequest.abort();
        }
        this.quoteRequest = new AbortController();
        var query = '?delivery_id=' + encodeURIComponent(this.deliveryId) + target + '&payment_id=' + encodeURIComponent(this.paymentId());
        var input = this.themeInput();
        if (input && input.length && !input.data('hide_front_delivery_price') && !input.data('is_free_delivery')) {
            this.captureTheme();
            input.closest('.fn_delivery_item').find('.fn_delivery_price').text(this.texts.cart_calculating || '');
        }
        fetch(url + query, { credentials: 'same-origin', signal: this.quoteRequest.signal })
            .then(function (response) { return response.json(); })
            .then(function (data) { self.showQuote(data || {}); })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    self.showQuote({ problem: 'unreachable' });
                }
            });
    };

    Block.prototype.showQuote = function (data) {
        var lines = [];
        var problem = data.problem === 'unreachable' || data.problem === 'rejected' || data.problem === 'busy' || data.problem === 'no_city_ref' || data.problem === 'no_uah';
        var priced = typeof data.price === 'number' && !data.unpriced;
        if (typeof data.price === 'number') {
            this.setThemePrice(data.price, data.totalWithDelivery, data.priceText || '', !!data.free);
            this.showUnpricedSummary();
        } else {
            // Ціни немає (безкоштовно, приховано, немає відправника, хибні дані або збій): повертаються власні числа
            // теми.
            this.restoreTheme();
            var input = this.themeInput();
            if (problem && input && input.length && !input.data('hide_front_delivery_price') && !input.data('is_free_delivery')) {
                input.closest('.fn_delivery_item').find('.fn_delivery_price').text(this.texts.cart_price_by_manager || '');
            }
        }
        if (data.term) {
            var date = new Date(data.term + 'T12:00:00');
            var lang = document.documentElement.lang || 'uk';
            lines.push(format(this.texts.cart_term, { date: isNaN(date) ? data.term : date.toLocaleDateString(lang, { day: 'numeric', month: 'long' }) }));
        }
        if (this.mode === 'address' && data.problem === 'no_city_ref') {
            lines.push(this.texts.cart_no_address);
        } else if (data.problem === 'rejected' && !priced) {
            lines.push(this.texts.cart_price_rejected);
        } else if (problem && !priced && data.problem !== 'no_city_ref') {
            lines.push(this.texts.cart_price_unreachable);
        }
        this.codFeeText = data.codFeeText || null;
        this.showCodLines();
        this.showLifting(data);
        this.quoteBox.textContent = '';
        var self = this;
        lines.forEach(function (line) { self.quoteBox.appendChild(el('p', null, line)); });
        this.quoteBox.classList.toggle('np-cart__quote--problem', problem && !priced);
        this.quoteBox.hidden = lines.length === 0;
    };

    Block.prototype.bindAddress = function () {
        var self = this;
        if (!this.street) {
            return;
        }
        this.street.addEventListener('input', function () {
            // Введена вручну вулиця дозволена, але не має Ref Нової Пошти: тоді відправлення йде за текстом.
            self.streetRef.value = '';
            self.showChoice();
            clearTimeout(self.streetTimer);
            self.streetTimer = setTimeout(function () { self.findStreets(); }, DEBOUNCE_MS);
        });
        this.street.addEventListener('blur', function () { setTimeout(function () { self.streetSuggest.hidden = true; }, 150); });
        [this.house, this.flat].forEach(function (input) {
            input.addEventListener('input', function () { self.showChoice(); });
        });
        var requote = function () {
            clearTimeout(self.quoteTimer);
            self.quoteTimer = setTimeout(function () { self.requestQuote(); }, DEBOUNCE_MS);
        };
        this.floor.addEventListener('input', requote);
        this.elevator.addEventListener('change', requote);
        this.liftingInput.addEventListener('change', function () {
            self.showElevator();
            requote();
        });
        var count = function () {
            var left = Math.max(0, 40 - self.note.value.length);
            self.noteCount.textContent = format(self.texts.cart_note_left, { left: left });
        };
        this.note.addEventListener('input', count);
        count();
        [this.street, this.house, this.flat, this.floor, this.note, root(this, '.np-cart__entrance-input')].forEach(function (input) {
            if (input) {
                syncFilled(input);
                input.addEventListener('blur', function () { syncFilled(input); });
            }
        });
    };

    function root(block, selector) {
        return block.root.querySelector(selector);
    }

    Block.prototype.findStreets = function () {
        var self = this;
        var query = this.street.value.trim();
        var url = route('Sviat_NovaPoshta_cart_streets');
        this.streetHint.hidden = true;
        if (!this.settlement || query.length < 2 || !url) {
            this.streetSuggest.hidden = true;
            return;
        }
        if (this.streetRequest) {
            this.streetRequest.abort();
        }
        this.streetRequest = new AbortController();
        fetch(url + '?settlement_id=' + encodeURIComponent(this.settlement.id) + '&q=' + encodeURIComponent(query), { credentials: 'same-origin', signal: this.streetRequest.signal })
            .then(function (response) { return response.json(); })
            .then(function (data) { self.showStreets(data || {}); })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    self.showStreets({ streets: [], problem: 'unreachable' });
                }
            });
    };

    /** Відповідь із вулицями, що ще в дорозі, належить населеному пункту, для якого її запитали. */
    Block.prototype.stopStreets = function () {
        clearTimeout(this.streetTimer);
        if (this.streetRequest) {
            this.streetRequest.abort();
            this.streetRequest = null;
        }
        this.streetSuggest.hidden = true;
        this.streetHint.hidden = true;
    };

    Block.prototype.showStreets = function (data) {
        var self = this;
        var streets = data.streets || [];
        this.streetSuggest.textContent = '';
        streets.forEach(function (street) {
            var option = el('li', 'np-cart__suggest-item', street.name);
            option.setAttribute('role', 'option');
            option.addEventListener('mousedown', function (event) {
                event.preventDefault();
                clearTimeout(self.streetTimer);
                self.street.value = street.name;
                self.streetRef.value = street.ref;
                syncFilled(self.street);
                self.streetSuggest.hidden = true;
                self.showChoice();
            });
            self.streetSuggest.appendChild(option);
        });
        this.streetSuggest.hidden = streets.length === 0;
        var hint = data.problem ? this.texts.cart_street_failed : (streets.length === 0 ? this.texts.cart_no_street : '');
        this.streetHint.textContent = hint || '';
        this.streetHint.hidden = !hint;
    };

    /** Підйом на поверх пропонується, лише коли він коштує додатково або якісь ваги невідомі (вирішує сервер). */
    Block.prototype.showLifting = function (data) {
        if (!this.lifting) {
            return;
        }
        this.lifting.hidden = !data.liftingOffered;
        if (!data.liftingOffered) {
            // Прихований, але ще позначений чекбокс надіслався б і вимагав би поверх.
            this.liftingInput.checked = false;
            this.elevator.checked = false;
            this.showElevator();
            return;
        }
        this.lifting.querySelector('.np-cart__lifting-fee').textContent = data.liftingFeeText ? '(' + data.liftingFeeText + ')' : '';
        this.lifting.querySelector('.np-cart__lifting-note').textContent = data.liftingFeeText ? this.texts.cart_lifting_paid : this.texts.cart_lifting_unknown;
    };

    /** "Є ліфт" має значення лише тоді, коли замовлено підйом на поверх. */
    Block.prototype.showElevator = function () {
        var row = this.elevator.closest('.np-cart__elevator-check');
        row.hidden = !this.liftingInput.checked;
        if (row.hidden) {
            this.elevator.checked = false;
        }
    };

    Block.prototype.say = function (text) {
        this.message.textContent = text || '';
        this.message.hidden = !text;
    };

    function visibleColor(value) {
        return value && value !== 'transparent' && !/rgba\([^)]*,\s*0\)$/.test(value) ? value : null;
    }

    /** Колір, що означає "вибрано": не чорний, білий чи сірий — ними теми фарбують звичайний текст і лінії. */
    function accentColor(value) {
        var color = visibleColor(value);
        var rgb = color ? color.match(/\d+(\.\d+)?/g) : null;
        if (!rgb || rgb.length < 3) {
            return null;
        }
        var r = Number(rgb[0]), g = Number(rgb[1]), b = Number(rgb[2]);
        return Math.max(r, g, b) - Math.min(r, g, b) > 24 ? color : null;
    }

    function isDark(color) {
        var rgb = color.match(/\d+(\.\d+)?/g).map(Number);
        return 0.299 * rgb[0] + 0.587 * rgb[1] + 0.114 * rgb[2] < 160;
    }

    /**
     * Колір, яким тема позначає вибрану опцію: позначка вибраного способу доставки (svg-галочка
     * або стилізована радіокнопка), інакше кнопка оформлення замовлення. Не перший .form__button: це
     * кнопка купона, бліда в деяких темах.
     */
    function themeAccent() {
        var chosen = document.querySelector('input[name="delivery_id"]:checked');
        var label = chosen ? (chosen.closest('label') || document.querySelector('label[for="' + chosen.id + '"]')) : null;
        var found = null;
        if (label) {
            label.querySelectorAll('svg, svg *').forEach(function (node) {
                var style = getComputedStyle(node);
                found = found || accentColor(style.stroke) || accentColor(style.fill);
            });
            [chosen, label].forEach(function (node) {
                ['::before', '::after'].forEach(function (pseudo) {
                    var style = getComputedStyle(node, pseudo);
                    found = found || accentColor(style.backgroundColor) || accentColor(style.borderTopColor);
                });
            });
            found = found || accentColor(getComputedStyle(chosen).accentColor);
        }
        var button = found ? null : document.querySelector('[name="checkout"].form__button, button[name="checkout"]');
        if (button) {
            var style = getComputedStyle(button);
            // Градієнтна кнопка має фірмовий колір у градієнті або на рамці.
            var gradient = (style.backgroundImage.match(/rgba?\([^)]*\)/g) || []);
            found = accentColor(style.backgroundColor) || accentColor(gradient[gradient.length - 1]) || accentColor(gradient[0]) || accentColor(style.borderTopColor);
        }
        return found;
    }

    /**
     * Не всі теми на основі okay_shop виставляють свої кольори як CSS-змінні (деякі теми й зібрані
     * стенди прибирають їх під час компіляції), тож колір і заокруглення зчитуються зі сторінки.
     * vibe_shop нічого не потребує: блок використовує його компонент чіпа.
     */
    function adoptThemeColours(root) {
        if (!root.classList.contains('np-cart--okay')) {
            return;
        }
        var accent = themeAccent();
        if (accent) {
            root.style.setProperty('--np-accent', accent);
            root.style.setProperty('--np-accent-text', isDark(accent) ? '#fff' : '#222');
        }
        var input = root.querySelector('.np-cart__city-input');
        if (input) {
            var style = getComputedStyle(input);
            var line = visibleColor(style.borderTopColor);
            if (line) {
                root.style.setProperty('--np-border', line);
            }
            root.style.setProperty('--np-radius', style.borderTopLeftRadius);
        }
    }

    var blocks = [];
    var rulesAdded = false;

    /**
     * Перевірки полів Нової Пошти у власному jQuery Validate теми (як робив NovaposhtaCost), щоб
     * повідомлення стояло під своїм полем ще до надсилання форми, усі одразу. Поля інших способів
     * доставки тема вимикає, а приховані кроки невидимі, тож валідатор пропускає і ті, й інші.
     * Сервер перевіряє все ще раз.
     */
    function addRules() {
        var $ = window.jQuery;
        var form = $ ? $('.fn_validate_cart') : null;
        if (rulesAdded || !form || !form.length || !$.validator || !form.data('validator') || blocks.length === 0) {
            return;
        }
        rulesAdded = true;
        var blockOf = function (element) {
            var root = element.closest('.np-cart');
            return root ? root.npBlock : null;
        };
        $.validator.addMethod('npCity', function (value, element) {
            var block = blockOf(element);
            return !block || !!block.settlement;
        });
        $.validator.addMethod('npPoint', function (value, element) {
            var block = blockOf(element);
            return !block || !!block.division;
        });
        blocks.forEach(function (block) {
            var texts = block.texts;
            $(block.city).rules('add', { npCity: true, messages: { npCity: texts.error_city } });
            if (block.pointCheck) {
                $(block.pointCheck).rules('add', { npPoint: true, messages: { npPoint: texts.error_point } });
            }
            if (block.street) {
                $(block.street).rules('add', { required: true, minlength: 2, messages: { required: texts.error_street, minlength: texts.error_street } });
            }
            if (block.house) {
                $(block.house).rules('add', { required: true, messages: { required: texts.error_house } });
            }
            if (block.floor) {
                $(block.floor).rules('add', {
                    required: function () { return !!(block.liftingInput && block.liftingInput.checked && !block.lifting.hidden); },
                    digits: true,
                    range: [1, 200],
                    messages: { required: texts.error_lifting_floor, digits: texts.error_floor, range: texts.error_floor }
                });
            }
        });
    }

    window.SviatNovaPoshtaCart = { addRules: addRules };

    function init() {
        document.querySelectorAll('.np-cart').forEach(function (root) {
            adoptThemeColours(root);
            blocks.push(new Block(root));
        });
        addRules();
        // Після змін у кошику тема повторно запускає `change` на вибраній доставці (jQuery
        // trigger, якого звичайні слухачі не бачать): вага й сума могли змінитися.
        if (window.jQuery) {
            window.jQuery(document).on('change', 'input[name="delivery_id"]', function () {
                blocks.forEach(function (block) {
                    // Тема позначає лише вибраний спосіб доставки: тепер його колір можна зчитати.
                    adoptThemeColours(block.root);
                    block.showUnpricedSummary();
                    block.showCodLines();
                    if (block.settlement) {
                        block.load();
                        block.requestQuote();
                    }
                });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

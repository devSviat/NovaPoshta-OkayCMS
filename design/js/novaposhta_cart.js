/*
 * Nova Poshta pickup point choice in the cart. Everything that comes from the dictionary is
 * put into the page with textContent only: names are third-party data.
 */
(function () {
    'use strict';

    var DEBOUNCE_MS = 200;

    function route(name) {
        // Themes declare `okay` with let/const: it is global but not a property of window.
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

    /** okay_shop-derived themes float the field label by a `filled` class they set on blur only. */
    function syncFilled(input) {
        if (input.parentNode) {
            input.parentNode.classList.toggle('filled', input.value.trim() !== '');
        }
    }

    function Block(root) {
        this.root = root;
        this.texts = parse(root.getAttribute('data-texts')) || {};
        this.chip = root.getAttribute('data-chip');
        this.chipSelected = root.getAttribute('data-chip-selected');
        this.city = root.querySelector('.np-cart__city-input');
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
        if (this.mode === 'address') {
            this.bindAddress();
        }
        if (this.settlement) {
            if (this.address) {
                this.address.hidden = false;
            }
            this.showChoice();
            this.load();
            this.requestQuote();
        }
    }

    Block.prototype.bind = function () {
        var self = this;
        this.root.querySelectorAll('[data-np-city]').forEach(function (button) {
            button.addEventListener('click', function () {
                self.chooseSettlement({ id: Number(button.getAttribute('data-np-city')), name: button.getAttribute('data-np-city-name') });
            });
        });
        this.city.addEventListener('input', function () {
            if (self.settlement && self.city.value.trim() !== self.settlement.name) {
                self.dropSettlement();
            }
            clearTimeout(self.cityTimer);
            self.cityTimer = setTimeout(function () { self.findCities(); }, DEBOUNCE_MS);
        });
        this.city.addEventListener('keydown', function (event) { self.cityKeys(event); });
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
            option.appendChild(el('b', null, item.name));
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
        this.city.value = settlement.name;
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
                // Streets belong to one settlement: a street of the previous one must not travel along.
                this.street.value = '';
                this.streetRef.value = '';
                syncFilled(this.street);
            }
        }
        if (this.address) {
            this.address.hidden = false;
        }
        this.showChoice();
        this.load();
        if (this.mode === 'address') {
            this.requestQuote();
        }
    };

    /** Edited city text no longer names the chosen place: the order must not go there silently. */
    Block.prototype.dropSettlement = function () {
        if (this.pointsRequest) {
            this.pointsRequest.abort();
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
        this.points.hidden = false;
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
        (point.problems || []).forEach(function (problem) {
            notes.push(self.texts['cart_' + problem]);
        });
        if (!unfit && point.closesToday) {
            notes.push(point.closesToday === '24/7' ? this.texts.cart_all_day : format(this.texts.cart_closes, { time: point.closesToday }));
        }
        if (notes.length) {
            option.appendChild(el('span', 'np-cart__option-note', notes.join(' · ')));
        }

        option.addEventListener('click', function () {
            if (unfit) {
                return;
            }
            self.division = { id: point.id, title: point.title + (point.place ? ': ' + point.place : '') };
            self.divisionInput.value = point.id;
            self.list.querySelectorAll('.np-cart__option').forEach(function (other) {
                other.classList.remove('np-cart__option--selected');
                other.setAttribute('aria-selected', 'false');
            });
            option.classList.add('np-cart__option--selected');
            option.setAttribute('aria-selected', 'true');
            self.showChoice();
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
                var place = [this.settlement.name, street, house].join(', ') + (flat ? ', ' + (this.texts.cart_flat_short || '') + ' ' + flat : '');
                this.choice.textContent = format(this.texts.cart_address_choice, { place: place });
                this.choice.hidden = false;
            } else {
                this.choice.hidden = true;
            }
            return;
        }
        if (this.settlement && this.division) {
            this.choice.textContent = format(this.texts.cart_choice, { place: this.settlement.name + ', ' + this.division.title });
            this.choice.hidden = false;
        } else {
            this.choice.hidden = true;
        }
    };

    /** The theme's own radio for this delivery method (jQuery: themes keep prices in jQuery data). */
    Block.prototype.themeInput = function () {
        var $ = window.jQuery;
        return $ ? $('input[name="delivery_id"][value="' + String(this.deliveryId).replace(/[^0-9]/g, '') + '"]') : null;
    };

    /** Writes a price where the theme reads it, then lets the theme recount its totals. */
    /** Remembers what the theme showed before the module touched it, to give it back later. */
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
        }
    };

    Block.prototype.clearQuote = function () {
        if (this.quoteRequest) {
            this.quoteRequest.abort();
            this.quoteRequest = null;
        }
        this.quoteBox.hidden = true;
        this.quoteBox.textContent = '';
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
                + '&lifting=' + (this.liftingInput && this.liftingInput.checked && !this.lifting.hidden ? 1 : 0)
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
        if (typeof data.price === 'number') {
            this.setThemePrice(data.price, data.totalWithDelivery, data.priceText || '', !!data.free);
        } else {
            // No price (free, hidden, no sender, bad input or a failure): the theme's own numbers come back.
            this.restoreTheme();
            var input = this.themeInput();
            if (problem && input && input.length && !input.data('hide_front_delivery_price') && !input.data('is_free_delivery')) {
                input.closest('.fn_delivery_item').find('.fn_delivery_price').text(this.texts.cart_price_by_manager || '');
            }
        }
        if (data.codFeeText) {
            lines.push(format(this.texts.cart_cod_fee, { fee: data.codFeeText }));
        }
        if (data.term) {
            var date = new Date(data.term + 'T12:00:00');
            var lang = document.documentElement.lang || 'uk';
            lines.push(format(this.texts.cart_term, { date: isNaN(date) ? data.term : date.toLocaleDateString(lang, { day: 'numeric', month: 'long' }) }));
        }
        if (this.mode === 'address' && data.problem === 'no_city_ref') {
            lines.push(this.texts.cart_no_address);
        } else if (data.problem === 'rejected' && typeof data.price !== 'number') {
            lines.push(this.texts.cart_price_rejected);
        } else if (problem && typeof data.price !== 'number' && data.problem !== 'no_city_ref') {
            lines.push(this.texts.cart_price_unreachable);
        }
        this.showLifting(data);
        this.quoteBox.textContent = '';
        var self = this;
        lines.forEach(function (line) { self.quoteBox.appendChild(el('p', null, line)); });
        this.quoteBox.classList.toggle('np-cart__quote--problem', problem && typeof data.price !== 'number');
        this.quoteBox.hidden = lines.length === 0;
    };

    Block.prototype.bindAddress = function () {
        var self = this;
        if (!this.street) {
            return;
        }
        this.street.addEventListener('input', function () {
            // A typed street is allowed, but it has no Nova Poshta Ref: the shipment then goes by text.
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
        this.liftingInput.addEventListener('change', requote);
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

    Block.prototype.showStreets = function (data) {
        var self = this;
        var streets = data.streets || [];
        this.streetSuggest.textContent = '';
        streets.forEach(function (street) {
            var option = el('li', 'np-cart__suggest-item', street.name);
            option.setAttribute('role', 'option');
            option.addEventListener('mousedown', function (event) {
                event.preventDefault();
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

    /** Lifting is offered only when it costs extra or some weights are unknown (server decides). */
    Block.prototype.showLifting = function (data) {
        if (!this.lifting) {
            return;
        }
        this.lifting.hidden = !data.liftingOffered;
        if (!data.liftingOffered) {
            // A hidden, still ticked box would be posted and then demand a floor.
            this.liftingInput.checked = false;
            this.elevator.checked = false;
            return;
        }
        this.lifting.querySelector('.np-cart__lifting-fee').textContent = data.liftingFeeText ? '(' + data.liftingFeeText + ')' : '';
        this.lifting.querySelector('.np-cart__lifting-note').textContent = data.liftingFeeText ? this.texts.cart_lifting_paid : this.texts.cart_lifting_unknown;
    };

    Block.prototype.say = function (text) {
        this.message.textContent = text || '';
        this.message.hidden = !text;
    };

    function visibleColor(value) {
        return value && value !== 'transparent' && !/rgba\([^)]*,\s*0\)$/.test(value) ? value : null;
    }

    /**
     * okay_shop-derived themes do not all expose their colours as CSS variables (broken_new
     * compiles them away), so the colours are read from the theme's own checkout button and
     * input on this page. vibe_shop needs nothing: the block uses its chip component.
     */
    function adoptThemeColours(root) {
        if (!root.classList.contains('np-cart--okay')) {
            return;
        }
        var button = document.querySelector('form .form__button, .form__button');
        var input = root.querySelector('.np-cart__city-input');
        if (button) {
            var style = getComputedStyle(button);
            // A flat button has a background colour; a gradient one (broken_new) has its brand
            // colour on the border, or as the first stop of the gradient.
            var gradient = (style.backgroundImage.match(/rgba?\([^)]*\)/) || [])[0];
            var border = visibleColor(style.borderTopColor);
            var accent = visibleColor(style.backgroundColor)
                || (border && border !== style.color ? border : null)
                || visibleColor(gradient);
            if (accent) {
                root.style.setProperty('--np-accent', accent);
                root.style.setProperty('--np-accent-text', style.color);
            }
        }
        if (input) {
            var line = visibleColor(getComputedStyle(input).borderTopColor);
            if (line) {
                root.style.setProperty('--np-border', line);
            }
        }
    }

    function init() {
        var blocks = [];
        document.querySelectorAll('.np-cart').forEach(function (root) {
            adoptThemeColours(root);
            blocks.push(new Block(root));
        });
        // The theme re-fires `change` on the chosen delivery after the cart changes (jQuery
        // trigger, which plain listeners do not see): the weight and total may have changed.
        if (window.jQuery) {
            window.jQuery(document).on('change', 'input[name="delivery_id"]', function () {
                blocks.forEach(function (block) {
                    if (block.settlement) {
                        block.load();
                        block.requestQuote();
                    }
                });
            });
            // Cash on delivery is a payment method (author's decision): its commission changes the price.
            window.jQuery(document).on('change', 'input[name="payment_method_id"]', function () {
                blocks.forEach(function (block) {
                    block.requestQuote();
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

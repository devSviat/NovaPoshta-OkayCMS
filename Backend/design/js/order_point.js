/* The point/address editor of the order page's Nova Poshta block (stage 4a2). Its fields carry no
   name: they sit inside the order's form and are posted to OrderPointAdmin on their own. */
(function () {
    'use strict';

    var DEBOUNCE_MS = 300;

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

    function format(template, values) {
        return String(template || '').replace(/%(\w+)%/g, function (all, name) {
            return values[name] !== undefined ? values[name] : all;
        });
    }

    function post(root, action, data) {
        var body = new URLSearchParams(Object.assign({ action: action, session_id: root.getAttribute('data-token') }, data));
        return fetch(root.getAttribute('data-url'), { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (response) { return response.json(); });
    }

    function field(label, input, errorFor) {
        var wrap = el('label', 'np-op__field');
        wrap.appendChild(el('span', 'heading_label', label));
        wrap.appendChild(input);
        if (errorFor) {
            var error = el('span', 'np-op__error');
            error.setAttribute('data-for', errorFor);
            wrap.appendChild(error);
        }
        return wrap;
    }

    function input(placeholder, maxLength) {
        var node = el('input', 'form-control');
        node.type = 'text';
        node.autocomplete = 'off';
        if (placeholder) {
            node.placeholder = placeholder;
        }
        if (maxLength) {
            node.maxLength = maxLength;
        }
        return node;
    }

    /** [[from, to, hours], …] (0 = Monday) or true, as the cart shows it. */
    function scheduleText(schedule, texts) {
        if (schedule === true) {
            return texts.op_all_day;
        }
        if (!Array.isArray(schedule)) {
            return '';
        }
        var days = String(texts.op_weekdays || '').split(',');
        return schedule.map(function (group) {
            var span = days[group[0]] + (group[1] !== group[0] ? '–' + days[group[1]] : '');
            return span + ' ' + (group[2] || texts.op_day_off);
        }).join(', ');
    }

    function Editor(root) {
        this.root = root;
        this.texts = JSON.parse(root.getAttribute('data-texts') || '{}');
        this.mode = root.getAttribute('data-mode');
        this.box = root.querySelector('.np-op__editor');
        this.settlement = root.getAttribute('data-settlement-id') ? { id: root.getAttribute('data-settlement-id'), name: root.getAttribute('data-settlement-name') } : null;
        this.group = null;
        this.divisionId = null;
        this.streetRef = '';
        this.timers = {};
        this.build();
    }

    Editor.prototype.debounce = function (name, fn) {
        clearTimeout(this.timers[name]);
        this.timers[name] = setTimeout(fn, DEBOUNCE_MS);
    };

    Editor.prototype.build = function () {
        var self = this;
        var t = this.texts;
        this.box.textContent = '';

        this.city = input(t.op_city_placeholder, 60);
        this.city.value = this.settlement ? this.settlement.name : '';
        this.citySuggest = el('ul', 'np-op__suggest');
        this.citySuggest.hidden = true;
        var cityField = field(t.op_city, this.city, 'settlement_id');
        cityField.appendChild(this.citySuggest);
        this.box.appendChild(cityField);
        this.city.addEventListener('input', function () {
            self.settlement = null;
            self.debounce('city', function () { self.findCities(); });
        });

        if (this.mode === 'address') {
            this.buildAddress();
        } else {
            this.chips = el('div', 'np-op__chips');
            this.box.appendChild(this.chips);
            this.search = input(t.op_point_search_placeholder, 60);
            this.box.appendChild(field(t.op_point_search, this.search, 'division_id'));
            this.search.addEventListener('input', function () {
                self.debounce('points', function () { self.loadPoints(); });
            });
            this.list = el('div', 'np-op__list');
            this.box.appendChild(this.list);
            this.unfit = el('div', 'np-op__unfit');
            this.unfit.hidden = true;
            this.box.appendChild(this.unfit);
            if (this.settlement) {
                this.loadPoints();
            }
        }

        var actions = el('div', 'np-op__actions');
        this.saveButton = el('button', 'btn btn_small btn_blue', t.op_save);
        this.saveButton.type = 'button';
        this.saveButton.addEventListener('click', function () { self.save(); });
        actions.appendChild(this.saveButton);
        if (!this.root.getAttribute('data-open')) {
            var cancel = el('button', 'btn btn_small btn_border_blue', t.op_cancel);
            cancel.type = 'button';
            cancel.addEventListener('click', function () { self.box.hidden = true; self.box.textContent = ''; });
            actions.appendChild(cancel);
        }
        this.box.appendChild(actions);
        this.box.hidden = false;
    };

    Editor.prototype.buildAddress = function () {
        var self = this;
        var t = this.texts;
        this.street = input('', 100);
        this.streetSuggest = el('ul', 'np-op__suggest');
        this.streetSuggest.hidden = true;
        var streetField = field(t.op_street, this.street, 'street');
        streetField.appendChild(this.streetSuggest);
        this.box.appendChild(streetField);
        this.street.addEventListener('input', function () {
            // A typed street has no Ref: the waybill then goes as a text address.
            self.streetRef = '';
            self.debounce('street', function () { self.findStreets(); });
        });
        var row = el('div', 'np-op__grid');
        this.house = input('', 10);
        this.flat = input('', 10);
        this.entrance = input('', 5);
        row.appendChild(field(t.op_house, this.house, 'house'));
        row.appendChild(field(t.op_flat, this.flat, 'flat'));
        row.appendChild(field(t.op_entrance, this.entrance, 'entrance'));
        this.box.appendChild(row);
        this.note = input('', 40);
        this.box.appendChild(field(t.op_note, this.note, 'note'));
        if (this.root.getAttribute('data-lifting')) {
            var lifting = el('label', 'np_check');
            this.lifting = el('input');
            this.lifting.type = 'checkbox';
            lifting.appendChild(this.lifting);
            lifting.appendChild(el('span', '', ' ' + t.op_lifting));
            this.box.appendChild(lifting);
            var floorRow = el('div', 'np-op__grid');
            floorRow.hidden = true;
            this.floor = input('', 3);
            this.floor.inputMode = 'numeric';
            floorRow.appendChild(field(t.op_floor, this.floor, 'floor'));
            var elevator = el('label', 'np_check');
            this.elevator = el('input');
            this.elevator.type = 'checkbox';
            elevator.appendChild(this.elevator);
            elevator.appendChild(el('span', '', ' ' + t.op_elevator));
            floorRow.appendChild(elevator);
            this.box.appendChild(floorRow);
            this.lifting.addEventListener('change', function () { floorRow.hidden = !self.lifting.checked; });
        }
    };

    Editor.prototype.suggest = function (list, items, label, pick) {
        list.textContent = '';
        items.forEach(function (item) {
            var li = el('li', '', label(item));
            li.addEventListener('mousedown', function (event) {
                event.preventDefault();
                list.hidden = true;
                pick(item);
            });
            list.appendChild(li);
        });
        list.hidden = items.length === 0;
    };

    Editor.prototype.findCities = function () {
        var self = this;
        var query = this.city.value.trim();
        if (query.length < 2) {
            this.citySuggest.hidden = true;
            return;
        }
        post(this.root, 'settlements', { q: query, mode: this.mode }).then(function (answer) {
            self.suggest(self.citySuggest, answer.settlements || [], function (s) {
                return [s.name, s.district, s.area].filter(Boolean).join(', ');
            }, function (s) {
                self.settlement = { id: String(s.id), name: s.name };
                self.city.value = [s.name, s.area].filter(Boolean).join(', ');
                self.divisionId = null;
                if (self.mode === 'address') {
                    self.street.value = '';
                    self.streetRef = '';
                } else {
                    self.group = null;
                    self.search.value = '';
                    self.loadPoints();
                }
            });
        }).catch(function () {});
    };

    Editor.prototype.findStreets = function () {
        var self = this;
        var query = this.street.value.trim();
        if (!this.settlement || query.length < 2) {
            this.streetSuggest.hidden = true;
            return;
        }
        post(this.root, 'streets', { settlement_id: this.settlement.id, q: query }).then(function (answer) {
            self.suggest(self.streetSuggest, answer.streets || [], function (s) { return s.name; }, function (s) {
                self.street.value = s.name;
                self.streetRef = s.ref;
            });
        }).catch(function () {});
    };

    Editor.prototype.loadPoints = function () {
        var self = this;
        if (!this.settlement) {
            return;
        }
        post(this.root, 'points', {
            order_id: this.root.getAttribute('data-order'),
            settlement_id: this.settlement.id,
            group_id: this.group || '',
            q: this.search.value.trim(),
        }).then(function (answer) { self.renderPoints(answer); }).catch(function () {});
    };

    Editor.prototype.renderPoints = function (answer) {
        var self = this;
        var t = this.texts;
        this.group = answer.group || null;
        this.chips.textContent = '';
        (answer.groups || []).forEach(function (group) {
            var chip = el('button', 'np-op__chip' + (String(group.id) === String(self.group) ? ' np-op__chip--on' : ''), group.name);
            chip.type = 'button';
            chip.addEventListener('click', function () {
                self.group = group.id;
                self.loadPoints();
            });
            self.chips.appendChild(chip);
        });
        this.list.textContent = '';
        var points = answer.divisions || [];
        if (points.length === 0) {
            this.list.appendChild(el('div', 'text_grey np-op__empty', t.op_no_points));
        }
        points.forEach(function (point) {
            var row = el('button', 'np-op__option' + (String(point.id) === String(self.divisionId) ? ' np-op__option--on' : ''));
            row.type = 'button';
            row.appendChild(el('b', '', point.title || ''));
            if (point.place) {
                row.appendChild(el('span', 'np-op__place', point.place));
            }
            var hours = scheduleText(point.schedule, t);
            if (hours) {
                row.appendChild(el('span', 'np-op__hours', hours));
            }
            var problem = (point.problems || [])[0];
            if (problem) {
                row.appendChild(el('span', 'np-op__problem', t['op_unfit_' + problem] || ''));
            }
            row.addEventListener('click', function () {
                self.divisionId = point.id;
                self.list.querySelectorAll('.np-op__option--on').forEach(function (other) { other.classList.remove('np-op__option--on'); });
                row.classList.add('np-op__option--on');
                self.unfit.textContent = problem ? t['op_unfit_' + problem] : '';
                self.unfit.hidden = !problem;
            });
            self.list.appendChild(row);
        });
        if (answer.total > points.length) {
            this.list.appendChild(el('div', 'text_grey np-op__empty', format(t.op_shown_of, { shown: points.length, total: answer.total })));
        }
    };

    Editor.prototype.data = function () {
        var data = { order_id: this.root.getAttribute('data-order'), settlement_id: this.settlement ? this.settlement.id : '' };
        if (this.mode === 'address') {
            data.street = this.street.value;
            data.street_ref = this.streetRef;
            data.house = this.house.value;
            data.flat = this.flat.value;
            data.entrance = this.entrance.value;
            data.note = this.note.value;
            if (this.lifting && this.lifting.checked) {
                data.lifting = '1';
                data.floor = this.floor.value;
                if (this.elevator.checked) {
                    data.elevator = '1';
                }
            }
        } else {
            data.division_id = this.divisionId || '';
        }
        return data;
    };

    Editor.prototype.save = function () {
        var self = this;
        var t = this.texts;
        var message = this.root.querySelector('.np-op__message');
        message.textContent = '';
        if (this.mode !== 'address' && !this.divisionId) {
            showAlert(message, 'error', t.op_choose_point);
            return;
        }
        this.saveButton.disabled = true;
        this.saveButton.textContent = t.op_saving;
        post(this.root, 'save', this.data()).then(function (answer) {
            if (!answer.ok) {
                showAlert(message, 'error', answer.error || t.op_network);
                return;
            }
            replaceBlocks(self.root, answer);
        }).catch(function () {
            showAlert(message, 'error', t.op_network);
        }).finally(function () {
            self.saveButton.disabled = false;
            self.saveButton.textContent = t.op_save;
        });
    };

    function showAlert(box, kind, text, button) {
        var alert = el('div', 'alert alert--' + kind + ' mb-h');
        var content = el('div', 'alert__content');
        content.appendChild(el('p', '', text));
        if (button) {
            content.appendChild(button);
        }
        alert.appendChild(content);
        box.appendChild(alert);
    }

    /** The core's order form posts delivery_price on save: it must hold the price written here. */
    function syncOrderForm(price) {
        var priceInput = document.querySelector('form [name="delivery_price"]');
        if (priceInput && price !== null && price !== undefined) {
            priceInput.value = Number(price).toFixed(2);
        }
    }

    function fresh(html, selector) {
        var holder = document.createElement('template');
        holder.innerHTML = (html || '').trim();
        return holder.content.querySelector(selector) || holder.content.firstElementChild;
    }

    function replaceBlocks(root, answer) {
        var block = fresh(answer.deliveryHtml, '.fn_np_order_point');
        if (!block) {
            return;
        }
        root.replaceWith(block);
        var waybill = document.querySelector('.fn_np_waybill');
        var newWaybill = fresh(answer.waybillHtml, '.fn_np_waybill');
        if (waybill && newWaybill) {
            waybill.replaceWith(newWaybill);
        } else if (waybill) {
            waybill.remove();
        } else if (newWaybill) {
            block.insertAdjacentElement('afterend', newWaybill);
        }
        showPrice(block, answer);
    }

    function showPrice(block, answer) {
        var texts = JSON.parse(block.getAttribute('data-texts') || '{}');
        var message = block.querySelector('.np-op__message');
        if (answer.warning) {
            showAlert(message, 'warning', answer.warning);
        }
        var price = answer.price;
        if (!price) {
            return;
        }
        var amount = price.price !== null && price.price !== undefined ? Number(price.price).toFixed(2) + ' ' + (block.getAttribute('data-currency') || '') : '';
        if (price.action === 'offer') {
            var apply = el('button', 'btn btn_small btn_border_blue', format(texts.op_price_apply, { price: amount }));
            apply.type = 'button';
            apply.addEventListener('click', function () {
                apply.disabled = true;
                post(block, 'apply_price', { order_id: block.getAttribute('data-order') }).then(function (result) {
                    if (result.ok) {
                        syncOrderForm(result.price && result.price.price);
                        replaceBlocks(block, result);
                    } else {
                        apply.disabled = false;
                        showAlert(message, 'error', result.error || texts.op_network);
                    }
                }).catch(function () {
                    apply.disabled = false;
                    showAlert(message, 'error', texts.op_network);
                });
            });
            showAlert(message, 'info', format(texts.op_price_offer, { price: amount }), apply);
        } else if (price.action === 'applied') {
            showAlert(message, 'success', format(texts.op_price_applied, { price: amount }));
        } else {
            if (price.action === 'auto') {
                syncOrderForm(price.price);
            }
            var text = price.action === 'keep' && amount ? texts.op_price_same : texts['op_price_' + price.action];
            if (text) {
                showAlert(message, price.action === 'keep' && !amount ? 'warning' : 'success', format(text, { price: amount }));
            }
        }
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.fn_np_op_change');
        var root = button ? button.closest('.fn_np_order_point') : null;
        if (root) {
            event.preventDefault();
            new Editor(root);
        }
    });

    function openWaiting() {
        document.querySelectorAll('.fn_np_order_point[data-open="1"]').forEach(function (root) {
            new Editor(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', openWaiting);
    } else {
        openWaiting();
    }
})();

/* The waybill block of an order page. Its fields carry data-field (not name): they live inside the
   order's form and must not be saved with it; they are posted to WaybillAdmin on their own. */
(function () {
    'use strict';

    function rootOf(el) {
        return el.closest('.fn_np_waybill');
    }

    /** The recipient is the customer in the order's own fields above, as they are now (saved or not). */
    function syncRecipient(root) {
        var form = root.closest('form');
        var value = function (name) {
            var input = form ? form.querySelector('input[name="' + name + '"]') : null;
            return input ? input.value.trim() : null;
        };
        var name = value('name');
        var lastName = value('last_name');
        var phone = value('phone');
        if (name === null && lastName === null && phone === null) {
            return;
        }
        var full = [lastName, name].filter(Boolean).join(' ');
        var setText = function (selector, text) {
            var node = root.querySelector(selector);
            if (node) {
                node.textContent = text;
            }
        };
        var setField = function (field, text) {
            var input = root.querySelector('[data-field="' + field + '"]');
            if (input) {
                input.value = text;
            }
        };
        setText('.fn_np_wb_who_name', full);
        setText('.fn_np_wb_who_phone', phone || '');
        setField('recipient_name', full);
        setField('phone', phone || '');
    }

    function fields(root) {
        syncRecipient(root);
        var data = {};
        root.querySelectorAll('[data-field]').forEach(function (input) {
            if (input.type === 'radio') {
                if (input.checked) {
                    data[input.getAttribute('data-field')] = input.value;
                }
            } else if (input.type === 'checkbox') {
                if (input.checked) {
                    data[input.getAttribute('data-field')] = '1';
                }
            } else {
                data[input.getAttribute('data-field')] = input.value;
            }
        });
        return data;
    }

    function clearIssues(root) {
        root.querySelectorAll('.np-wb__error').forEach(function (span) { span.textContent = ''; });
        root.querySelectorAll('.np-wb__invalid').forEach(function (input) { input.classList.remove('np-wb__invalid'); });
        root.querySelectorAll('.np-wb__issues').forEach(function (box) { box.textContent = ''; });
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text) {
            node.textContent = text;
        }
        return node;
    }

    /** Field problems go under their field; the rest into the issues box, NP's code tucked away. */
    function showIssues(root, issues, box, heading) {
        var target = box || root.querySelector('.np-wb__issues');
        issues.forEach(function (issue) {
            var span = issue.field ? root.querySelector('.np-wb__error[data-for="' + issue.field + '"]') : null;
            if (span) {
                // The advice names the problem in the admin's language; NP's own text stays at hand on hover.
                span.textContent = issue.advice;
                span.title = [issue.message, issue.details].filter(Boolean).join(' — ');
                var input = root.querySelector('[data-field="' + issue.field + '"]');
                if (input) {
                    input.classList.add('np-wb__invalid');
                }
                return;
            }
            if (!target) {
                return;
            }
            var alert = el('div', 'alert alert--error np-wb__issue');
            var content = el('div', 'alert__content');
            content.appendChild(el('p', 'text_600', heading || ''));
            if (issue.orderUrl) {
                var link = el('a', '', issue.orderText);
                link.href = issue.orderUrl;
                link.target = '_blank';
                var line = el('p', '');
                line.appendChild(link);
                content.appendChild(line);
            } else if (issue.message) {
                content.appendChild(el('p', '', issue.message));
            }
            content.appendChild(el('p', 'text_grey', root.getAttribute('data-what-to-do') + ' ' + issue.advice));
            if (issue.details) {
                var details = el('details');
                details.appendChild(el('summary', '', root.getAttribute('data-details')));
                details.appendChild(el('div', 'text_grey', issue.details));
                content.appendChild(details);
            }
            alert.appendChild(content);
            target.appendChild(alert);
        });
        var first = root.querySelector('.np-wb__invalid, .np-wb__issue');
        if (first && first.scrollIntoView) {
            first.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }

    function showNotices(root, notices) {
        var box = root.querySelector('.np-wb__notices');
        (notices || []).forEach(function (text) {
            var alert = el('div', 'alert alert--warning');
            var content = el('div', 'alert__content');
            content.appendChild(el('p', '', text));
            alert.appendChild(content);
            box.appendChild(alert);
        });
    }

    function send(root, button, action, extra, issuesBox) {
        if (button.disabled) {
            return;
        }
        clearIssues(root);
        // Icon buttons keep their SVG: the label is restored as markup.
        var label = button.innerHTML;
        button.disabled = true;
        if (action === 'create') {
            button.textContent = root.getAttribute('data-busy');
        }
        var body = new URLSearchParams(Object.assign({ action: action, order_id: root.getAttribute('data-order'), session_id: root.getAttribute('data-token') }, extra));
        fetch(root.getAttribute('data-url'), { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (answer) {
                // The card lives in the main column, the form beside it: a new state reloads the page.
                if (answer.ok && action !== 'refresh') {
                    window.location.reload();
                    return;
                }
                if (answer.ok && typeof answer.html === 'string') {
                    var holder = document.createElement('template');
                    holder.innerHTML = answer.html.trim();
                    var fresh = holder.content.firstElementChild;
                    if (fresh) {
                        root.replaceWith(fresh);
                        showNotices(fresh, answer.notices);
                        return;
                    }
                }
                showIssues(root, answer.issues || [], issuesBox, root.getAttribute('data-failed-' + action));
                if (action === 'remove') {
                    offerUnlink(root);
                }
            })
            .catch(function () {
                showIssues(root, [{ field: null, message: null, advice: root.getAttribute('data-network'), details: '' }], issuesBox, root.getAttribute('data-failed-' + action));
            })
            .finally(function () {
                button.disabled = false;
                button.innerHTML = label;
            });
    }

    /** Nova Poshta would not delete it (gone from its account, or already accepted): the order can still let it go. */
    function offerUnlink(root) {
        var box = root.querySelector('.np-wb__issues');
        if (!box || box.querySelector('.fn_np_wb_unlink')) {
            return;
        }
        var button = el('button', 'btn btn_small btn_border_blue fn_np_wb_unlink', root.getAttribute('data-unlink'));
        button.type = 'button';
        box.appendChild(button);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.fn_np_wb_create, .fn_np_wb_attach, .fn_np_wb_remove, .fn_np_wb_unlink, .fn_np_wb_refresh, .fn_np_wb_lookup');
        var root = button ? rootOf(button) : null;
        if (!root) {
            return;
        }
        event.preventDefault();
        if (button.classList.contains('fn_np_wb_create')) {
            send(root, button, 'create', fields(root));
        } else if (button.classList.contains('fn_np_wb_attach')) {
            var number = root.querySelector('[data-attach="number"]');
            send(root, button, 'attach', { number: number ? number.value : '' }, root.querySelector('.np-wb__issues--attach'));
        } else if (button.classList.contains('fn_np_wb_refresh')) {
            send(root, button, 'refresh', {});
        } else if (button.classList.contains('fn_np_wb_lookup')) {
            send(root, button, 'lookup', {});
        } else if (button.classList.contains('fn_np_wb_unlink')) {
            if (window.confirm(root.getAttribute('data-confirm-unlink'))) {
                send(root, button, 'unlink', {});
            }
        } else {
            var question = root.getAttribute(button.getAttribute('data-attached') ? 'data-confirm-unlink' : 'data-confirm-remove');
            if (window.confirm(question)) {
                send(root, button, 'remove', {});
            }
        }
    });

    document.addEventListener('change', function (event) {
        var input = event.target;
        var root = input.closest ? rootOf(input) : null;
        if (!root || input.type !== 'checkbox') {
            return;
        }
        var part = input.getAttribute('data-field') === 'cod' ? '.np-wb__cod' : input.getAttribute('data-field') === 'lifting' ? '.np-wb__lifting' : null;
        if (part && root.querySelector(part)) {
            root.querySelector(part).hidden = !input.checked;
        }
        if (input.getAttribute('data-field') === 'cod' && root.querySelector('[data-field="cod_amount"]')) {
            root.querySelector('[data-field="cod_amount"]').disabled = !input.checked;
        }
    });

    /** Delivery inside the order total plus the recipient paying NP for it again: say so before creating. */
    document.addEventListener('change', function (event) {
        var input = event.target;
        if (!input.matches || !input.matches('[data-field="payer_type"]')) {
            return;
        }
        var root = rootOf(input);
        var warning = root && root.querySelector('.np-wb__double');
        if (!warning) {
            return;
        }
        var recipient = root.querySelector('[data-field="payer_type"][value="Recipient"]');
        warning.hidden = !(recipient && recipient.checked);
    });

    // The core binds .fn_clipboard once on page load; cards re-rendered later need their own handler.
    document.addEventListener('click', function (event) {
        var link = event.target.closest('.fn_np_wb_copy');
        if (!link) {
            return;
        }
        event.preventDefault();
        var value = link.getAttribute('data-copy-string');
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value);
        } else {
            var field = document.createElement('input');
            field.value = value;
            field.setAttribute('readonly', '');
            field.style.position = 'absolute';
            field.style.opacity = '0';
            document.body.appendChild(field);
            field.select();
            document.execCommand('copy');
            document.body.removeChild(field);
        }
        link.classList.add('is-copied');
        setTimeout(function () { link.classList.remove('is-copied'); }, 1000);
    });

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('.fn_np_wb_more');
        var panel = toggle ? toggle.closest('.np-wb__card') && toggle.closest('.np-wb__card').querySelector('.np-wb__more') : null;
        if (!panel) {
            return;
        }
        var opening = toggle.getAttribute('aria-expanded') !== 'true';
        if (event.detail > 0) {
            toggle.blur();
        }
        toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        toggle.querySelector('span').textContent = toggle.getAttribute(opening ? 'data-less' : 'data-more');
        if (panel.np_slide) {
            panel.np_slide.cancel();
        }
        panel.hidden = false;
        var height = panel.scrollHeight + 'px';
        var still = !panel.animate || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (still) {
            panel.hidden = !opening;
            return;
        }
        panel.np_slide = panel.animate(
            opening ? [{ height: '0px' }, { height: height }] : [{ height: height }, { height: '0px' }],
            { duration: 200, easing: 'ease-in-out' }
        );
        panel.np_slide.onfinish = function () {
            panel.np_slide = null;
            panel.hidden = !opening;
        };
    });

    document.addEventListener('click', function (event) {
        var hide = event.target.closest('.fn_np_mass_hide');
        if (hide) {
            hide.closest('.fn_np_mass_report').hidden = true;
        }
    });

    document.addEventListener('change', function (event) {
        var input = event.target;
        if (!input.matches || !input.matches('[data-field="recipient_type"]')) {
            return;
        }
        var root = rootOf(input);
        var org = input.value === 'Organization' && input.checked;
        root.querySelector('.np-wb__org').hidden = !org;
        var label = root.querySelector('.np-wb__who-label');
        label.textContent = label.getAttribute(org ? 'data-org' : 'data-private');
    });

    document.addEventListener('input', function (event) {
        var input = event.target;
        if (!input.matches || !input.matches('input[name="name"], input[name="last_name"], input[name="phone"]')) {
            return;
        }
        var form = input.closest('form');
        var root = form ? form.querySelector('.fn_np_waybill') : null;
        if (root) {
            syncRecipient(root);
        }
    });
})();

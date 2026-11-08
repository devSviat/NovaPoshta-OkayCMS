{* Waybill of the order: the card when there is one, the form otherwise. Inside the order's own
   form, so the fields carry data-field instead of name: saving the order must not post them. *}
{if $np_waybill}
{function name=np_wb_attach}
<details class="np-wb__attach">
    <summary class="btn btn_border_blue">{$btr->sviat__novaposhta__waybill_attach_open|escape}</summary>
    <div class="np-wb__attach-row">
        <input class="form-control" data-attach="number" inputmode="numeric" maxlength="20" placeholder="{$btr->sviat__novaposhta__waybill_attach_placeholder|escape}">
        <button type="button" class="btn btn_small btn_border_blue fn_np_wb_attach">{$btr->sviat__novaposhta__waybill_attach|escape}</button>
    </div>
    <div class="text_grey">{$btr->sviat__novaposhta__waybill_attach_note|escape}</div>
    <div class="np-wb__issues np-wb__issues--attach" role="alert"></div>
</details>
{/function}
{function name=np_wb_tip}<i class="fn_tooltips np-wb__tip" title="{$text|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>{/function}
{function name=np_wb_switch}
<label class="np-wb__switch">
    <span class="switch switch-default"><input class="switch-input" type="checkbox" data-field="{$field|escape}" value="1" {if $on}checked{/if}><span class="switch-label"></span><span class="switch-handle"></span></span>
    <span>{$label|escape}</span>
</label>
{/function}
{* form="np-wb-none" keeps the radios out of the order form: saving the order must not post them. *}
{* An audit value in words: NP answers in API codes (Recipient, WarehouseDoors…). *}
{function name=np_wb_audit_value kind='' v=''}{if $kind == 'cod_payer' && $v == 'Sender'}{$btr->sviat__novaposhta__waybill_sender_party|escape}{elseif $kind == 'cod_payer' && $v == 'Recipient'}{$btr->sviat__novaposhta__waybill_recipient|escape}{elseif $kind == 'service_type' && $v != ''}{$btr->getTranslation('sviat__novaposhta__waybill_service_'|cat:$v)|escape}{else}{$v|escape}{/if}{/function}
{function name=np_wb_radios}
<div class="np-wb__radios" role="radiogroup">
    {foreach $options as $v => $label}
        <div class="okay_type_radio_wrap">
            <input id="np_wb_{$field|escape}_{$v|escape}" class="hidden_check" form="np-wb-none" name="np_wb_{$field|escape}" type="radio" data-field="{$field|escape}" value="{$v|escape}" {if $value == $v}checked{/if}>
            <label for="np_wb_{$field|escape}_{$v|escape}" class="okay_type_radio"><span>{$label|escape}</span></label>
        </div>
    {/foreach}
</div>
{/function}
{$w = $np_waybill}
<div class="np-wb fn_np_waybill" data-url="{$smarty.server.SCRIPT_NAME|escape}?controller=Sviat.NovaPoshta.WaybillAdmin" data-order="{$w.orderId|escape}" data-token="{$smarty.session.id|escape}"
     data-confirm-remove="{$btr->sviat__novaposhta__waybill_confirm_remove|escape}" data-confirm-unlink="{$btr->sviat__novaposhta__waybill_confirm_unlink|escape}" data-unlink="{$btr->sviat__novaposhta__waybill_unlink|escape}"
     data-busy="{$btr->sviat__novaposhta__waybill_creating|escape}" data-failed-create="{$btr->sviat__novaposhta__waybill_failed_create|escape}" data-failed-attach="{$btr->sviat__novaposhta__waybill_failed_attach|escape}" data-failed-remove="{$btr->sviat__novaposhta__waybill_failed_remove|escape}" data-failed-refresh="{$btr->sviat__novaposhta__waybill_failed_refresh|escape}" data-what-to-do="{$btr->sviat__novaposhta__waybill_what_to_do|escape}"
     data-details="{$btr->sviat__novaposhta__waybill_details|escape}" data-network="{$btr->sviat__novaposhta__waybill_network|escape}">
    <div class="np-wb__head">
        <span class="np-wb__title">{if $w.row || $w.refusal || $w.check}{$btr->sviat__novaposhta__waybill_title|escape}{else}{$btr->sviat__novaposhta__waybill_form_title|escape}{/if}</span>
    </div>
    <div class="np-wb__notices" role="status"></div>

    {if $w.row}
        {$r = $w.row}
        {$c = $r.card}
        <div class="np-wb__card">
            {if $c.created}<div class="text_grey font_12">{$btr->sviat__novaposhta__card_created|escape} {$c.created|escape}</div>{/if}
            <div class="np-wb__number">
                <a href="{$c.link|escape}" target="_blank" rel="noopener noreferrer">{$c.numberSpaced|escape} ↗</a>
                {if $c.statusKey}<span class="np-wb__badge np-wb__badge--{$c.statusGroup|escape}">{$btr->getTranslation($c.statusKey)|escape}</span>{/if}
            </div>
            {if $c.description || $c.announced}
                <div class="np-wb__cells">
                    {if $c.description}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_description|escape}</span>{$c.description|escape}</div>{/if}
                    {if $c.announced}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_announced|escape}</span><b>{$c.announced|escape}</b></div>{/if}
                </div>
            {/if}
            {if $c.route}
                <div class="np-wb__section np-wb__route">
                    {if $c.route.from}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_from|escape}</span>{$c.route.from|escape}</div>{/if}
                    {if $c.route.status}<div class="np-wb__status">{$c.route.status|escape}</div>{/if}
                    {if $c.route.plan}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_plan|escape}</span>{$c.route.plan|escape}</div>{/if}
                    {if $c.route.to}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_to|escape}</span>{$c.route.to|escape}</div>{/if}
                </div>
            {/if}
            {if $c.recipient && ($c.recipient.name || $c.recipient.phone)}
                <div class="np-wb__section">
                    <div class="np-wb__group">{$btr->sviat__novaposhta__card_recipient|escape}</div>
                    {if $c.recipient.name}<div>{$c.recipient.name|escape}</div>{/if}
                    {if $c.recipient.phone}<div>{$c.recipient.phone|escape}</div>{/if}
                    {if $c.recipient.received}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_received|escape}</span>{$c.recipient.received|escape}</div>{/if}
                </div>
            {/if}
            {if $c.services && ($c.services.redelivery || $c.services.control || $c.services.returnOrRedirect)}
                <div class="np-wb__section">
                    <div class="np-wb__group">{$btr->sviat__novaposhta__card_services|escape}</div>
                    {if $c.services.redelivery}
                        <div><span class="np-wb__label">{$btr->sviat__novaposhta__card_redelivery|escape}</span><b>{$c.services.redelivery|escape}</b>{if $c.services.redeliveryPayer}, {$btr->sviat__novaposhta__card_fee_payer|escape} {$btr->getTranslation($c.services.redeliveryPayer)|escape}{/if}{if $c.services.redeliveryNumber}, № {$c.services.redeliveryNumber|escape}{/if}</div>
                    {/if}
                    {if $c.services.control}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_control|escape}</span><b>{$c.services.control|escape}</b></div>{/if}
                    {if $c.services.returnOrRedirect}<div><span class="np-wb__label">{$btr->getTranslation($c.services.returnOrRedirect.kind)|escape}</span>{$c.services.returnOrRedirect.number|escape}</div>{/if}
                </div>
            {/if}
            {if $c.payment && ($c.payment.cost || $c.payment.payer)}
                <div class="np-wb__section">
                    <div class="np-wb__group">{$btr->sviat__novaposhta__card_payment|escape}</div>
                    {if $c.payment.cost}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_cost|escape}</span><b>{$c.payment.cost|escape}</b></div>{/if}
                    {if $c.payment.payer}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_payer|escape}</span>{$btr->getTranslation($c.payment.payer)|escape}{if $c.payment.method}, {$btr->getTranslation($c.payment.method)|escape}{/if}</div>{/if}
                </div>
            {elseif $r.services.cod}
                <div class="np-wb__line"><span class="text_600">{$btr->sviat__novaposhta__waybill_cod|escape}:</span> {$r.services.codAmount|escape} ₴ ({if $r.services.codKind == 'control'}{$btr->sviat__novaposhta__waybill_cod_control|escape}{else}{$btr->sviat__novaposhta__waybill_cod_money|escape}{/if})</div>
            {/if}
            {if $r.services.lifting}
                <div class="np-wb__line"><span class="text_600">{$btr->sviat__novaposhta__order_lifting|escape}:</span> {$r.services.floor|escape}</div>
            {/if}
            {if $r.attached}
                <div class="text_grey">{$btr->sviat__novaposhta__waybill_attached_note|escape}</div>
            {elseif $r.audit}
                {if $r.audit.status == 'verified'}
                    <div class="np-wb__ok">{$btr->sviat__novaposhta__waybill_audit_ok|escape}</div>
                {elseif $r.audit.status == 'unverified'}
                    <div class="alert alert--warning"><div class="alert__content"><p>{$btr->sviat__novaposhta__waybill_audit_unverified|escape}</p></div></div>
                {else}
                    {foreach $r.audit.findings as $f}
                        <div class="alert alert--error" role="alert"><div class="alert__content">
                            <p><b>{if $f.kind == 'cod'}{$btr->sviat__novaposhta__waybill_find_cod|escape}{elseif $f.kind == 'cod_payer'}{$btr->sviat__novaposhta__waybill_find_cod_payer|escape}{elseif $f.kind == 'control'}{$btr->sviat__novaposhta__waybill_find_control|escape}{elseif $f.kind == 'point'}{$btr->sviat__novaposhta__waybill_find_point|escape}{elseif $f.kind == 'address'}{$btr->sviat__novaposhta__waybill_find_address|escape}{elseif $f.kind == 'city'}{$btr->sviat__novaposhta__waybill_find_city|escape}{else}{$btr->sviat__novaposhta__waybill_find_service_type|escape}{/if}</b></p>
                            <p>{$btr->sviat__novaposhta__waybill_find_expected|escape}: {np_wb_audit_value kind=$f.kind v=$f.expected}. {$btr->sviat__novaposhta__waybill_find_actual|escape}: {np_wb_audit_value kind=$f.kind v=$f.actual}.</p>
                            <p class="text_grey">{if $f.kind == 'control'}{$btr->sviat__novaposhta__waybill_find_control_advice|escape}{elseif $f.kind == 'cod_payer'}{$btr->sviat__novaposhta__waybill_find_cod_payer_advice|escape}{else}{$btr->sviat__novaposhta__waybill_find_advice|escape}{/if}</p>
                        </div></div>
                    {/foreach}
                {/if}
                {foreach $r.audit.unchecked as $kind}
                    <div class="text_grey">{if $kind == 'point'}{$btr->sviat__novaposhta__waybill_point_unchecked|escape}{else}{$btr->sviat__novaposhta__waybill_lifting_unchecked|escape}{/if}</div>
                {/foreach}
            {/if}
            {if $c.more}
                <details class="np-wb__more">
                    <summary>{$btr->sviat__novaposhta__card_more|escape}</summary>
                    {if $c.more.type}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_type|escape}</span>{$btr->getTranslation($c.more.type)|escape}</div>{/if}
                    {if $c.more.weight}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_weight|escape}</span>{$c.more.weight|escape}</div>{/if}
                    {if $c.more.volumeWeight}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_volume_weight|escape}</span>{$c.more.volumeWeight|escape}</div>{/if}
                    <div><span class="np-wb__label">{$btr->sviat__novaposhta__card_seats|escape}</span>{$c.more.seats|escape}</div>
                    {if $c.more.barcode}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_barcode|escape}</span>{$c.more.barcode|escape}</div>{/if}
                    {if $c.more.factualWeight}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_factual_weight|escape}</span>{$c.more.factualWeight|escape}</div>{/if}
                    {if $c.more.serviceType}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_service_type|escape}</span>{$btr->getTranslation($c.more.serviceType)|escape}</div>{/if}
                    {if $c.recipient && $c.recipient.address}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_address|escape}</span>{$c.recipient.address|escape}</div>{/if}
                </details>
            {/if}
            <div class="np-wb__issues" role="alert"></div>
            <div class="np-wb__footer">
                <span class="text_grey">{if $c.updated}{$btr->sviat__novaposhta__card_updated|escape} {$c.updated|escape}{else}{$btr->sviat__novaposhta__card_not_updated|escape}{/if}{if $c.checked && $c.checked != $c.updated}<br>{$btr->sviat__novaposhta__card_checked|escape} {$c.checked|escape}{/if}</span>
                <button type="button" class="np-wb__link fn_np_wb_refresh">{$btr->sviat__novaposhta__card_refresh|escape}</button>
            </div>
            <div class="np-wb__actions">
                <button type="button" class="btn btn_small btn-outline-danger fn_np_wb_remove" data-attached="{if $r.attached}1{/if}">{if $r.attached}{$btr->sviat__novaposhta__waybill_unlink|escape}{else}{$btr->sviat__novaposhta__waybill_remove|escape}{/if}</button>
            </div>
        </div>
    {elseif $w.check}
        <div class="alert alert--warning"><div class="alert__content">
            <div class="alert__title">{$btr->sviat__novaposhta__waybill_check_title|escape}</div>
            <p>{$btr->sviat__novaposhta__waybill_check_text|escape}</p>
        </div></div>
        <div class="np-wb__issues" role="alert"></div>
        <div class="np-wb__actions">
            <button type="button" class="btn btn-info fn_np_wb_lookup">{$btr->sviat__novaposhta__waybill_check_button|escape}</button>
        </div>
    {elseif $w.refusal}
        <div class="alert alert--warning"><div class="alert__content"><p>{$btr->getTranslation($w.refusalKey)|escape}</p></div></div>
        {np_wb_attach}
    {else}
        {$d = $w.draft}
        <div class="np-wb__form">
            {if $w.duplicatePoint}
                <div class="alert alert--warning"><div class="alert__content"><p>{$btr->sviat__novaposhta__waybill_duplicate_point|escape}</p></div></div>
            {/if}
            {if $w.textAddress}
                <div class="alert alert--warning"><div class="alert__content"><p>{$btr->sviat__novaposhta__waybill_text_address|escape}</p></div></div>
            {/if}

            {$org = $d->recipientType == 'Organization'}
            <div class="heading_label np-wb__label">{$btr->sviat__novaposhta__waybill_recipient_group|escape}</div>
            {np_wb_radios field='recipient_type' value=$d->recipientType options=['PrivatePerson' => $btr->sviat__novaposhta__waybill_recipient_private, 'Organization' => $btr->sviat__novaposhta__waybill_recipient_org]}
            <div class="np-wb__org" {if !$org}hidden{/if}>
                {if $d->fromInvoice}<div class="text_grey font_12">{$btr->sviat__novaposhta__waybill_from_invoice|escape}</div>{/if}
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_edrpou|escape}</span><input class="form-control" inputmode="numeric" data-field="edrpou" maxlength="20" value="{$d->edrpou|escape}"><span class="np-wb__error" data-for="edrpou"></span></label>
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_company|escape}{np_wb_tip text=$btr->sviat__novaposhta__waybill_company_tip}</span><input class="form-control" data-field="company_name" maxlength="100" value="{$d->companyName|escape}"><span class="np-wb__error" data-for="company_name"></span></label>
            </div>
            <div class="np-wb__who">
                <span class="np-wb__who-label" data-private="{$btr->sviat__novaposhta__waybill_who_private|escape}" data-org="{$btr->sviat__novaposhta__waybill_who_org|escape}">{if $org}{$btr->sviat__novaposhta__waybill_who_org|escape}{else}{$btr->sviat__novaposhta__waybill_who_private|escape}{/if}</span>
                <span class="text_600 fn_np_wb_who_name">{$d->recipientName|escape}</span>, <span class="fn_np_wb_who_phone">{$d->recipientPhone|escape}</span>
                <div class="text_grey font_12">{$btr->sviat__novaposhta__waybill_who_source|escape}</div>
                <input type="hidden" data-field="recipient_name" value="{$d->recipientName|escape}">
                <input type="hidden" data-field="phone" value="{$d->recipientPhone|escape}">
                <span class="np-wb__error" data-for="recipient_name"></span>
                <span class="np-wb__error" data-for="phone"></span>
            </div>

            <div class="np-wb__pair">
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_weight|escape}</span><input class="form-control" inputmode="decimal" data-field="weight" value="{$d->weightKg|escape}"><span class="np-wb__error" data-for="weight"></span></label>
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_volume|escape}</span><input class="form-control" inputmode="decimal" data-field="volume" value="{$d->volumeM3|escape}"><span class="np-wb__error" data-for="volume"></span></label>
            </div>
            {* The block name NovaPoshtaTracking had: Checkbox warns here that part of the order is prepaid. *}
            {get_design_block block="novaposhta_document_payment"}
            <div class="np-wb__pair">
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_declared_cost|escape}{np_wb_tip text=$btr->sviat__novaposhta__waybill_declared_tip}</span><input class="form-control" inputmode="decimal" data-field="declared_cost" value="{$d->declaredCost|escape}"><span class="np-wb__error" data-for="declared_cost"></span></label>
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_cod_amount|escape}{np_wb_tip text=$btr->sviat__novaposhta__waybill_cod_amount_tip}</span><input class="form-control" inputmode="decimal" data-field="cod_amount" value="{$d->codAmount|escape}" {if !$d->cod}disabled{/if}><span class="np-wb__error" data-for="cod_amount"></span></label>
            </div>
            {$control = $d->codKind->value == 'control'}
            <div class="heading_label np-wb__label">{if $control}{$btr->sviat__novaposhta__waybill_cod_control_title|escape}{np_wb_tip text=$btr->sviat__novaposhta__waybill_cod_control_tip}{else}{$btr->sviat__novaposhta__waybill_cod_money_title|escape}{np_wb_tip text=$btr->sviat__novaposhta__waybill_cod_money_tip}{/if}</div>
            <div class="np-wb__switch">
                <label class="switch switch-default{if $w.paid} switch-disabled{/if}">
                    <input class="switch-input" type="checkbox" data-field="cod" value="1" {if $d->cod}checked{/if} {if $w.paid}disabled{/if}>
                    <span class="switch-label"></span><span class="switch-handle"></span>
                </label>
                <span class="text_grey font_12">{if $w.paid}{$btr->sviat__novaposhta__waybill_cod_paid|escape}{elseif $w.codPayment}{$btr->sviat__novaposhta__waybill_cod_auto|escape}{else}{$btr->sviat__novaposhta__waybill_cod_manual|escape}{/if}</span>
            </div>
            <input type="hidden" data-field="cod_kind" value="{$d->codKind->value|escape}">
            {if !$control}
                <div class="np-wb__cod" {if !$d->cod}hidden{/if}>
                    <div class="heading_label np-wb__label">{$btr->sviat__novaposhta__waybill_cod_payer|escape}</div>
                    {np_wb_radios field='cod_payer' value=$d->codPayer options=['Sender' => $btr->sviat__novaposhta__waybill_sender_party, 'Recipient' => $btr->sviat__novaposhta__waybill_recipient]}
                </div>
            {/if}

            <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_description|escape}{np_wb_tip text=$btr->sviat__novaposhta__waybill_description_tip}</span><input class="form-control" data-field="description" maxlength="100" value="{$d->description|escape}"><span class="np-wb__error" data-for="description"></span></label>

            {if $d->mode->value == 'address'}
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_address_note|escape}</span><input class="form-control" data-field="address_note" maxlength="50" value="{$d->addressNote|escape}"></label>
                {np_wb_switch field='lifting' on=$d->lifting label=$btr->sviat__novaposhta__order_lifting}
                <div class="np-wb__pair np-wb__lifting" {if !$d->lifting}hidden{/if}>
                    <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__order_floor|escape}</span><input class="form-control" inputmode="numeric" data-field="floor" value="{$d->floor|escape}"><span class="np-wb__error" data-for="floor"></span></label>
                    {np_wb_switch field='elevator' on=$d->elevator label=$btr->sviat__novaposhta__order_elevator}
                </div>
            {/if}

            <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_cargo|escape}</span><select class="form-control" data-field="cargo_type">
                <option value="Parcel" {if $d->cargoType == 'Parcel'}selected{/if}>{$btr->sviat__novaposhta__waybill_cargo_parcel|escape}</option>
                <option value="Cargo" {if $d->cargoType == 'Cargo'}selected{/if}>{$btr->sviat__novaposhta__waybill_cargo_cargo|escape}</option>
                <option value="Documents" {if $d->cargoType == 'Documents'}selected{/if}>{$btr->sviat__novaposhta__waybill_cargo_documents|escape}</option>
            </select><span class="np-wb__error" data-for="cargo_type"></span></label>
            <div class="heading_label np-wb__label">{$btr->sviat__novaposhta__waybill_payer|escape}</div>
            {np_wb_radios field='payer_type' value=$d->payerType options=['Sender' => $btr->sviat__novaposhta__waybill_sender_party, 'Recipient' => $btr->sviat__novaposhta__waybill_recipient]}
            {if $d->deliveryInTotal > 0}
                {$np_wb_delivery = $d->deliveryInTotal|string_format:'%.2f'|replace:'.':','}
                <div class="np-wb__line text_grey">{$btr->sviat__novaposhta__waybill_payer_in_total|replace:'%sum%':$np_wb_delivery|escape}</div>
                <div class="alert alert--warning np-wb__double" role="alert" {if !($d->payerType == 'Recipient' && $d->cod)}hidden{/if}><div class="alert__content">{$btr->sviat__novaposhta__waybill_double_delivery|replace:'%sum%':$np_wb_delivery|escape}</div></div>
            {/if}
            <div class="heading_label np-wb__label">{$btr->sviat__novaposhta__waybill_payment|escape}</div>
            {np_wb_radios field='payment_method' value=$d->paymentMethod options=['Cash' => $btr->sviat__novaposhta__waybill_cash, 'NonCash' => $btr->sviat__novaposhta__waybill_noncash]}

            <div class="np-wb__line text_grey">{$btr->sviat__novaposhta__waybill_ship_date|escape}: {$d->shipDate|escape}</div>

            <div class="np-wb__issues" role="alert"></div>
            <div class="np-wb__actions">
                <button type="button" class="btn btn-info fn_np_wb_create">{$btr->sviat__novaposhta__waybill_create|escape}</button>
            </div>
            {np_wb_attach}
        </div>
    {/if}
</div>
{/if}

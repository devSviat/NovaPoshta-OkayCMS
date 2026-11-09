{* Накладна замовлення: картка, якщо вона є, інакше форма. Усередині власної форми
   замовлення, тож поля мають data-field замість name: збереження замовлення не повинне їх надсилати. *}
{if $np_waybill}
{* Прив'язка за номером: кнопка-перемикач серед дій, рядок номера в окремій панелі під ними, як у старому модулі. *}
{function name=np_wb_attach_toggle}<button type="button" class="btn btn_border_blue fn_np_wb_attach_toggle" aria-expanded="false">{$btr->sviat__novaposhta__waybill_attach_open|escape}</button>{/function}
{function name=np_wb_attach_panel}
<div class="np-wb__attach" hidden>
    <div class="np-wb__attach-row">
        <input class="form-control" data-attach="number" inputmode="numeric" maxlength="20" placeholder="{$btr->sviat__novaposhta__waybill_attach_placeholder|escape}">
        <button type="button" class="btn btn_small btn_border_blue fn_np_wb_attach">{$btr->sviat__novaposhta__waybill_attach|escape}</button>
    </div>
    <div class="text_grey font_12">{$btr->sviat__novaposhta__waybill_attach_note|escape}</div>
    <div class="np-wb__issues np-wb__issues--attach" role="alert"></div>
</div>
{/function}
{* Підказка відкривається від краю екрана: типово праворуч, ліворуч — для поля в правій колонці. *}
{function name=np_wb_tip side="left"}<i class="fn_tooltips np-wb__tip hint-bottom-{$side|escape}-t-white-s-med-mobile hint-anim" data-hint="{$text|escape}" tabindex="0" role="img" aria-label="{$text|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>{/function}
{function name=np_wb_switch}
<label class="np-wb__switch">
    <span class="switch switch-default"><input class="switch-input" type="checkbox" data-field="{$field|escape}" value="1" {if $on}checked{/if}><span class="switch-label"></span><span class="switch-handle"></span></span>
    <span>{$label|escape}</span>
</label>
{/function}
{* form="np-wb-none" виводить радіокнопки з форми замовлення: збереження замовлення не повинне їх надсилати. *}
{* Значення звірки словами: НП відповідає кодами API (Recipient, WarehouseDoors…). *}
{function name=np_wb_audit_value kind='' v=''}{if $kind == 'cod_payer' && $v == 'Sender'}{$btr->sviat__novaposhta__waybill_sender_party|escape}{elseif $kind == 'cod_payer' && $v == 'Recipient'}{$btr->sviat__novaposhta__waybill_recipient|escape}{elseif $kind == 'service_type' && $v != ''}{$btr->getTranslation('sviat__novaposhta__waybill_service_'|cat:$v)|escape}{else}{$v|escape}{/if}{/function}
{function name=np_wb_select}
<label class="np-wb__field"><span class="heading_label">{$label|escape}</span><select class="selectpicker form-control" data-field="{$field|escape}">
    {foreach $options as $v => $text}<option value="{$v|escape}" {if $value == $v}selected{/if}>{$text|escape}</option>{/foreach}
</select></label>
{/function}
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
     data-details="{$btr->sviat__novaposhta__waybill_details|escape}" data-network="{$btr->sviat__novaposhta__waybill_network|escape}"
     data-toast-ok="{$btr->toastr_success|escape}" data-toast-error="{$btr->toastr_error|escape}" data-done-create="{$btr->sviat__novaposhta__waybill_done_create|escape}" data-done-attach="{$btr->sviat__novaposhta__waybill_done_attach|escape}" data-done-refresh="{$btr->sviat__novaposhta__waybill_done_refresh|escape}" data-done-remove="{$btr->sviat__novaposhta__waybill_done_remove|escape}" data-done-unlink="{$btr->sviat__novaposhta__waybill_done_unlink|escape}">
    {if !$w.row}
    <div class="np-wb__head">
        <span class="np-wb__title">{if $w.refusal || $w.check}{$btr->sviat__novaposhta__waybill_title|escape}{else}{$btr->sviat__novaposhta__waybill_form_title|escape}{/if}</span>
    </div>
    {/if}
    <div class="np-wb__notices" role="status"></div>

    {if $w.row}
        {$r = $w.row}
        {$c = $r.card}
        {* Розкладка старої картки трекінгу: номер і опис угорі, блок маршруту ліворуч,
           отримувач праворуч, послуги й оплата нижче, оновлення й видалення у футері. *}
        <div class="np-wb__card">
            <div class="np-wb__top">
                <div class="np-wb__num">
                    {if $c.created}<span class="np-wb__muted">{$btr->sviat__novaposhta__card_created|escape} {$c.created|escape}</span>{/if}
                    <span class="np-wb__number"><a href="" class="np-wb__copy fn_clipboard fn_np_wb_copy hint-bottom-middle-t-info-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__card_copy|escape}" data-hint-copied="{$btr->sviat__novaposhta__card_copied|escape}" data-copy-string="{$c.number|escape}">{$c.numberSpaced|escape}</a><a class="np-wb__open" href="{$c.link|escape}" target="_blank" rel="noopener noreferrer" title="{$btr->sviat__novaposhta__card_open_np|escape}" aria-label="{$btr->sviat__novaposhta__card_open_np|escape}"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6"/><path d="M11 13l9 -9"/><path d="M15 4h5v5"/></svg></a></span>
                </div>
                {if $c.description}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_description|escape}</span><span class="np-wb__oneline" title="{$c.description|escape}">{$c.description|escape}</span></div>{/if}
                {if $c.announced}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_announced|escape}</span><b>{$c.announced|escape}</b></div>{/if}
            </div>
            <div class="np-wb__body">
                <div class="np-wb__route">
                    {if $c.route && $c.route.from}<div><b>{$btr->sviat__novaposhta__card_departure|escape}{if $c.route.departure}: {$c.route.departure|escape}{/if}</b><div class="np-wb__small">{$c.route.from|escape}</div></div>{/if}
                    {if $c.statusKey}<span class="np-wb__badge np-wb__badge--{$c.statusGroup|escape}{if $c.route && $c.route.status} hint-bottom-middle-t-info-s-small-mobile hint-anim{/if}"{if $c.route && $c.route.status} data-hint="{$c.route.status|escape}" tabindex="0"{/if}>{$btr->getTranslation($c.statusKey)|escape}</span>{/if}
                    {if $c.route && ($c.route.plan || $c.route.to)}<div><b>{$btr->sviat__novaposhta__card_plan_time|escape}{if $c.route.plan}: {$c.route.plan|escape}{/if}</b>{if $c.route.to}<div class="np-wb__small">{$c.route.to|escape}</div>{/if}</div>{/if}
                </div>
                {if $c.recipient}
                    <div class="np-wb__recip">
                        {if $c.recipient.name}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_recipient|escape}</span>{$c.recipient.name|escape}</div>{/if}
                        {if $c.recipient.phone}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_phone|escape}</span>{$c.recipient.phone|escape}</div>{/if}
                        {if $c.recipient.address}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_address|escape}</span>{$c.recipient.address|escape}</div>{/if}
                        {if $c.recipient.received}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_received|escape}</span>{$c.recipient.received|escape}</div>{/if}
                    </div>
                {/if}
            </div>
            {if $c.services && ($c.services.redelivery || $c.services.control || $c.services.returnOrRedirect)}
                <div class="np-wb__services">
                    <span class="np-wb__caps">{$btr->sviat__novaposhta__card_services|escape}</span>
                    <div>
                        {if $c.services.redelivery}<b>{$btr->sviat__novaposhta__card_redelivery|escape}: {$c.services.redelivery|escape}</b>{if $c.services.redeliveryPayer}, {$btr->sviat__novaposhta__card_fee_payer|escape} {$btr->getTranslation($c.services.redeliveryPayer)|escape}{/if}{if $c.services.redeliveryNumber}, № {$c.services.redeliveryNumber|escape}{/if}{/if}
                        {if $c.services.control}<b>{$btr->sviat__novaposhta__card_control|escape}: {$c.services.control|escape}</b>{/if}
                        {if $c.services.returnOrRedirect}<span> · {$btr->getTranslation($c.services.returnOrRedirect.kind)|escape}: {$c.services.returnOrRedirect.number|escape}</span>{/if}
                    </div>
                </div>
            {elseif $r.services.cod}
                <div class="np-wb__services"><span class="np-wb__caps">{$btr->sviat__novaposhta__card_services|escape}</span><div><b>{if $r.services.codKind == 'control'}{$btr->sviat__novaposhta__card_control|escape}{else}{$btr->sviat__novaposhta__card_redelivery|escape}{/if}: {$r.services.codAmount|escape} ₴</b></div></div>
            {/if}
            {if $r.services.lifting}
                <div class="np-wb__services"><span class="np-wb__caps">{$btr->sviat__novaposhta__order_lifting|escape}</span><div>{$r.services.floor|escape}</div></div>
            {/if}
            {if $c.payment && ($c.payment.cost || $c.payment.payer)}
                <div class="np-wb__pay">
                    {if $c.payment.cost}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_cost|escape}</span><b class="np-wb__big">{$c.payment.cost|escape}</b></div>{/if}
                    {if $c.payment.payer}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_payer|escape}</span><span class="np-wb__capital">{$btr->getTranslation($c.payment.payer)|escape}</span></div>{/if}
                    {if $c.payment.method}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_method|escape}</span>{$btr->getTranslation($c.payment.method)|escape}</div>{/if}
                </div>
            {/if}
            {if !$r.attached && $r.audit && $r.audit.status != 'verified'}
                <div class="np-wb__note">
                {if $r.audit.status == 'unverified'}
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
                </div>
            {/if}
            {if $c.more}
                <div class="np-wb__more" hidden><div class="np-wb__more-inner">
                    <div class="np-wb__more-title">{$btr->sviat__novaposhta__card_details|escape}</div>
                    <div class="np-wb__more-body">
                        {if $c.more.type}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_type|escape}</span>{$btr->getTranslation($c.more.type)|escape}</div>{/if}
                        {if $c.more.weight}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_weight|escape}</span>{$c.more.weight|escape}</div>{/if}
                        {if $c.more.volumeWeight}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_volume_weight|escape}</span>{$c.more.volumeWeight|escape}</div>{/if}
                        <div><span class="np-wb__label">{$btr->sviat__novaposhta__card_seats|escape}</span>{$c.more.seats|escape}</div>
                        {if $c.more.barcode}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_barcode|escape}</span>{$c.more.barcode|escape}</div>{/if}
                        {if $c.more.factualWeight}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_factual_weight|escape}</span>{$c.more.factualWeight|escape}</div>{/if}
                        {if $c.more.serviceType}<div><span class="np-wb__label">{$btr->sviat__novaposhta__card_service_type|escape}</span>{$btr->getTranslation($c.more.serviceType)|escape}</div>{/if}
                    </div>
                </div></div>
            {/if}
            <div class="np-wb__issues" role="alert"></div>
            <div class="np-wb__footer">
                {if $c.more}<button type="button" class="np-wb__more-toggle fn_np_wb_more" aria-expanded="false" data-more="{$btr->sviat__novaposhta__card_more|escape}" data-less="{$btr->sviat__novaposhta__card_less|escape}"><span>{$btr->sviat__novaposhta__card_more|escape}</span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6l6 -6"/></svg></button>{/if}
                <span class="np-wb__tools">
                    <span class="np-wb__muted">{if $c.updated}{$btr->sviat__novaposhta__card_updated|escape} {$c.updated|escape}{else}{$btr->sviat__novaposhta__card_not_updated|escape}{/if}</span>
                    <button type="button" class="np-wb__icon fn_np_wb_refresh hint-bottom-middle-t-info-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__card_refresh|escape}" aria-label="{$btr->sviat__novaposhta__card_refresh|escape}"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/></svg></button>
                    {if $r.attached}
                        <button type="button" class="np-wb__icon np-wb__icon--danger fn_np_wb_remove hint-bottom-right-t-info-s-small-mobile hint-anim" data-attached="1" data-hint="{$btr->sviat__novaposhta__waybill_unlink|escape}" aria-label="{$btr->sviat__novaposhta__waybill_unlink|escape}"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 15l6 -6"/><path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464"/><path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463"/><path d="M16 19h5"/></svg></button>
                    {elseif $r.deletable}
                        <button type="button" class="np-wb__icon np-wb__icon--danger fn_np_wb_remove hint-bottom-right-t-info-s-small-mobile hint-anim" data-attached="" data-hint="{$btr->sviat__novaposhta__waybill_remove|escape}" aria-label="{$btr->sviat__novaposhta__waybill_remove|escape}"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7l16 0"/><path d="M10 11l0 6"/><path d="M14 11l0 6"/><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/></svg></button>
                    {/if}
                </span>
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
        <div class="np-wb__actions">{np_wb_attach_toggle}</div>
        {np_wb_attach_panel}
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
            <div class="np-wb__group np-wb__group--first">{$btr->sviat__novaposhta__waybill_recipient_group|escape}</div>
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

            {* Посилка, далі хто платить НП за доставку, далі гроші, які НП збирає з отримувача. *}
            <div class="np-wb__group">{$btr->sviat__novaposhta__waybill_group_parcel|escape}</div>
            <div class="np-wb__pair">
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_cargo|escape}</span><select class="selectpicker form-control" data-field="cargo_type">
                    {foreach $d->cargoChoices() as $np_cargo}
                    {* Поштомат не приймає «Вантаж» (api-docs-ua, «ЕН на поштомат»). *}
                    <option value="{$np_cargo|escape}" {if $d->cargoType == $np_cargo}selected{/if}{if $d->locker && $np_cargo == 'Cargo'} disabled{/if}>{$btr->getTranslation('sviat__novaposhta__waybill_cargo_'|cat:($np_cargo|lower))|escape}</option>
                {/foreach}
                </select><span class="np-wb__error" data-for="cargo_type"></span></label>
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_declared_cost|escape}{np_wb_tip text=$btr->sviat__novaposhta__waybill_declared_tip side="right"}</span><input class="form-control" inputmode="decimal" data-field="declared_cost" value="{$d->declaredCost|escape}"><span class="np-wb__error" data-for="declared_cost"></span></label>
            </div>
            <div class="np-wb__pair">
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_weight|escape}</span><input class="form-control" inputmode="decimal" data-field="weight" value="{$d->weightKg|escape}"><span class="np-wb__error" data-for="weight"></span></label>
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_volume|escape}</span><input class="form-control" inputmode="decimal" data-field="volume" value="{$d->volumeM3|escape}"><span class="np-wb__error" data-for="volume"></span></label>
            </div>
            {if $d->locker}
                {$seat = $d->seat()}
                <div class="np-wb__locker">
                    <span class="heading_label">{$btr->sviat__novaposhta__waybill_locker_seat|escape}</span>
                    <div class="np-wb__triple">
                        <label class="np-wb__field"><span class="text_grey">{$btr->sviat__novaposhta__waybill_seat_length|escape}</span><input class="form-control" inputmode="numeric" data-field="seat_length" value="{$seat[0]|escape}"><span class="np-wb__error" data-for="seat_length"></span></label>
                        <label class="np-wb__field"><span class="text_grey">{$btr->sviat__novaposhta__waybill_seat_width|escape}</span><input class="form-control" inputmode="numeric" data-field="seat_width" value="{$seat[1]|escape}"><span class="np-wb__error" data-for="seat_width"></span></label>
                        <label class="np-wb__field"><span class="text_grey">{$btr->sviat__novaposhta__waybill_seat_height|escape}</span><input class="form-control" inputmode="numeric" data-field="seat_height" value="{$seat[2]|escape}"><span class="np-wb__error" data-for="seat_height"></span></label>
                    </div>
                    <span class="text_grey">{$btr->sviat__novaposhta__waybill_locker_hint|escape}</span>
                </div>
            {/if}
            <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_description|escape}{np_wb_tip text=$btr->sviat__novaposhta__waybill_description_tip}</span><input class="form-control" data-field="description" maxlength="100" value="{$d->description|escape}"><span class="np-wb__error" data-for="description"></span></label>
            <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_extra_info|escape}{np_wb_tip text=$btr->sviat__novaposhta__waybill_extra_info_tip}</span><input class="form-control" data-field="extra_info" maxlength="100" value="{$d->extraInfo|escape}"><span class="np-wb__error" data-for="extra_info"></span></label>
            {if $d->mode->value == 'address'}
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_address_note|escape}</span><input class="form-control" data-field="address_note" maxlength="50" value="{$d->addressNote|escape}"></label>
                {np_wb_switch field='lifting' on=$d->lifting label=$btr->sviat__novaposhta__order_lifting}
                <div class="np-wb__pair np-wb__lifting" {if !$d->lifting}hidden{/if}>
                    <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__order_floor|escape}</span><input class="form-control" inputmode="numeric" data-field="floor" value="{$d->floor|escape}"><span class="np-wb__error" data-for="floor"></span></label>
                    {np_wb_switch field='elevator' on=$d->elevator label=$btr->sviat__novaposhta__order_elevator}
                </div>
            {/if}
            <div class="np-wb__group">{$btr->sviat__novaposhta__waybill_group_delivery|escape}</div>
            <div class="np-wb__pair">
                {np_wb_select field='payer_type' value=$d->payerType label=$btr->sviat__novaposhta__waybill_payer options=['Sender' => $btr->sviat__novaposhta__waybill_sender_party, 'Recipient' => $btr->sviat__novaposhta__waybill_recipient]}
                {np_wb_select field='payment_method' value=$d->paymentMethod label=$btr->sviat__novaposhta__waybill_payment options=['Cash' => $btr->sviat__novaposhta__waybill_cash, 'NonCash' => $btr->sviat__novaposhta__waybill_noncash]}
            </div>
            {if $d->senderPaysBecause == 'free'}
                <div class="np-wb__line text_grey">{$btr->sviat__novaposhta__waybill_payer_free|escape}</div>
            {/if}
            {if $d->deliveryInTotal > 0}
                {$np_wb_delivery = $d->deliveryInTotal|string_format:'%.2f'|replace:'.00':''|replace:'.':','}
                <div class="np-wb__line text_grey">{$btr->sviat__novaposhta__waybill_payer_in_total|replace:'%sum%':$np_wb_delivery|escape}</div>
                <div class="alert alert--warning np-wb__double" role="alert" {if $d->payerType != 'Recipient'}hidden{/if}><div class="alert__content">{$btr->sviat__novaposhta__waybill_double_delivery|replace:'%sum%':$np_wb_delivery|escape}</div></div>
            {/if}
            {* Назва блоку, яку мав NovaPoshtaTracking: тут Checkbox попереджає, що частину замовлення передплачено. *}
            {get_design_block block="novaposhta_document_payment"}
            {$control = $d->codKind->value == 'control'}
            <div class="np-wb__group">{if $control}{$btr->sviat__novaposhta__waybill_cod_control_title|escape}{np_wb_tip text=$btr->sviat__novaposhta__waybill_cod_control_tip}{else}{$btr->sviat__novaposhta__waybill_cod_money_title|escape}{np_wb_tip text=$btr->sviat__novaposhta__waybill_cod_money_tip}{/if}</div>
            <div class="np-wb__switch">
                <label class="switch switch-default{if $w.paid} switch-disabled{/if}">
                    <input class="switch-input" type="checkbox" data-field="cod" value="1" {if $d->cod}checked{/if} {if $w.paid}disabled{/if}>
                    <span class="switch-label"></span><span class="switch-handle"></span>
                </label>
                <span class="text_grey font_12">{if $w.paid}{$btr->sviat__novaposhta__waybill_cod_paid|escape}{elseif $w.codPayment}{$btr->sviat__novaposhta__waybill_cod_auto|escape}{else}{$btr->sviat__novaposhta__waybill_cod_manual|escape}{/if}</span>
            </div>
            <input type="hidden" data-field="cod_kind" value="{$d->codKind->value|escape}">
            <div class="np-wb__pair">
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_cod_amount|escape}{np_wb_tip text=$btr->sviat__novaposhta__waybill_cod_amount_tip}</span><input class="form-control" inputmode="decimal" data-field="cod_amount" value="{$d->codAmount|escape}" {if !$d->cod}disabled{/if}><span class="np-wb__error" data-for="cod_amount"></span></label>
                {if !$control}
                    <div class="np-wb__cod" {if !$d->cod}hidden{/if}>
                        {np_wb_select field='cod_payer' value=$d->codPayer label=$btr->sviat__novaposhta__waybill_cod_fee_payer options=['Sender' => $btr->sviat__novaposhta__waybill_sender_party, 'Recipient' => $btr->sviat__novaposhta__waybill_recipient]}
                    </div>
                {/if}
            </div>

            <div class="np-wb__line text_grey">{$btr->sviat__novaposhta__waybill_ship_date|escape}: {$d->shipDate|escape}</div>

            <div class="np-wb__issues" role="alert"></div>
            <div class="np-wb__actions np-wb__actions--form">
                <button type="button" class="btn btn-info fn_np_wb_create">{$btr->sviat__novaposhta__waybill_create|escape}</button>
                {np_wb_attach_toggle}
            </div>
            {np_wb_attach_panel}
        </div>
    {/if}
</div>
{/if}

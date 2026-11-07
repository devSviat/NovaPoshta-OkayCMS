{* Waybill of the order: the card when there is one, the form otherwise. Inside the order's own
   form, so the fields carry data-field instead of name: saving the order must not post them. *}
{if $np_waybill}
{function name=np_wb_attach}
<details class="np-wb__attach">
    <summary>{$btr->sviat__novaposhta__waybill_attach_open|escape}</summary>
    <div class="np-wb__attach-row">
        <input class="form-control" data-attach="number" inputmode="numeric" maxlength="20" placeholder="{$btr->sviat__novaposhta__waybill_attach_placeholder|escape}">
        <button type="button" class="btn btn_small btn_border_blue fn_np_wb_attach">{$btr->sviat__novaposhta__waybill_attach|escape}</button>
    </div>
    <div class="text_grey">{$btr->sviat__novaposhta__waybill_attach_note|escape}</div>
    <div class="np-wb__issues np-wb__issues--attach" role="alert"></div>
</details>
{/function}
{$w = $np_waybill}
<div class="np-wb fn_np_waybill" data-url="{$smarty.server.SCRIPT_NAME|escape}?controller=Sviat.NovaPoshta.WaybillAdmin" data-order="{$w.orderId|escape}" data-token="{$smarty.session.id|escape}"
     data-confirm-remove="{$btr->sviat__novaposhta__waybill_confirm_remove|escape}" data-confirm-unlink="{$btr->sviat__novaposhta__waybill_confirm_unlink|escape}" data-unlink="{$btr->sviat__novaposhta__waybill_unlink|escape}"
     data-busy="{$btr->sviat__novaposhta__waybill_creating|escape}" data-failed-create="{$btr->sviat__novaposhta__waybill_failed_create|escape}" data-failed-attach="{$btr->sviat__novaposhta__waybill_failed_attach|escape}" data-failed-remove="{$btr->sviat__novaposhta__waybill_failed_remove|escape}" data-what-to-do="{$btr->sviat__novaposhta__waybill_what_to_do|escape}"
     data-details="{$btr->sviat__novaposhta__waybill_details|escape}" data-network="{$btr->sviat__novaposhta__waybill_network|escape}">
    <div class="np-wb__head">
        <span class="heading_label">{$btr->sviat__novaposhta__waybill_title|escape}</span>
        {if !$w.row}<span class="text_grey">{$btr->sviat__novaposhta__waybill_not_created|escape}</span>{/if}
    </div>
    <div class="np-wb__notices" role="status"></div>

    {if $w.row}
        {$r = $w.row}
        <div class="np-wb__card">
            <div class="np-wb__number"><a href="{$r.trackingUrl|escape}" target="_blank" rel="noopener noreferrer">{$r.numberSpaced|escape}</a></div>
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
                            <p>{$btr->sviat__novaposhta__waybill_find_expected|escape}: {$f.expected|escape}. {$btr->sviat__novaposhta__waybill_find_actual|escape}: {$f.actual|escape}.</p>
                            <p class="text_grey">{if $f.kind == 'control'}{$btr->sviat__novaposhta__waybill_find_control_advice|escape}{elseif $f.kind == 'cod_payer'}{$btr->sviat__novaposhta__waybill_find_cod_payer_advice|escape}{else}{$btr->sviat__novaposhta__waybill_find_advice|escape}{/if}</p>
                        </div></div>
                    {/foreach}
                {/if}
                {foreach $r.audit.unchecked as $kind}
                    <div class="text_grey">{if $kind == 'point'}{$btr->sviat__novaposhta__waybill_point_unchecked|escape}{else}{$btr->sviat__novaposhta__waybill_lifting_unchecked|escape}{/if}</div>
                {/foreach}
            {/if}
            {if $r.services.cod}
                <div class="np-wb__line"><span class="text_600">{$btr->sviat__novaposhta__waybill_cod|escape}:</span> {$r.services.codAmount|escape} ₴ ({if $r.services.codKind == 'control'}{$btr->sviat__novaposhta__waybill_cod_control|escape}{else}{$btr->sviat__novaposhta__waybill_cod_money|escape}{/if})</div>
            {/if}
            {if $r.services.lifting}
                <div class="np-wb__line"><span class="text_600">{$btr->sviat__novaposhta__order_lifting|escape}:</span> {$r.services.floor|escape}</div>
            {/if}
            <div class="np-wb__issues" role="alert"></div>
            <div class="np-wb__actions">
                <button type="button" class="btn btn_small btn-outline-danger fn_np_wb_remove" data-attached="{if $r.attached}1{/if}">{if $r.attached}{$btr->sviat__novaposhta__waybill_unlink|escape}{else}{$btr->sviat__novaposhta__waybill_remove|escape}{/if}</button>
            </div>
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

            <div class="np-wb__group">{$btr->sviat__novaposhta__waybill_recipient_group|escape}</div>
            <div class="np-wb__grid">
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_recipient_name|escape}</span><input class="form-control" data-field="recipient_name" maxlength="60" value="{$d->recipientName|escape}"><span class="np-wb__error" data-for="recipient_name"></span></label>
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_recipient_phone|escape}</span><input class="form-control" type="tel" data-field="phone" maxlength="20" value="{$d->recipientPhone|escape}"><span class="np-wb__error" data-for="phone"></span></label>
            </div>

            <div class="np-wb__group">{$btr->sviat__novaposhta__waybill_cargo_group|escape}</div>
            <div class="np-wb__grid">
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_weight|escape}</span><input class="form-control" inputmode="decimal" data-field="weight" value="{$d->weightKg|escape}"><span class="np-wb__error" data-for="weight"></span></label>
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_volume|escape}</span><input class="form-control" inputmode="decimal" data-field="volume" value="{$d->volumeM3|escape}"><span class="np-wb__error" data-for="volume"></span></label>
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_declared_cost|escape}</span><input class="form-control" inputmode="decimal" data-field="declared_cost" value="{$d->declaredCost|escape}"><span class="np-wb__error" data-for="declared_cost"></span></label>
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_cargo|escape}</span><select class="form-control" data-field="cargo_type">
                    <option value="Parcel" {if $d->cargoType == 'Parcel'}selected{/if}>{$btr->sviat__novaposhta__waybill_cargo_parcel|escape}</option>
                    <option value="Cargo" {if $d->cargoType == 'Cargo'}selected{/if}>{$btr->sviat__novaposhta__waybill_cargo_cargo|escape}</option>
                    <option value="Documents" {if $d->cargoType == 'Documents'}selected{/if}>{$btr->sviat__novaposhta__waybill_cargo_documents|escape}</option>
                </select><span class="np-wb__error" data-for="cargo_type"></span></label>
            </div>
            <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_description|escape}</span><input class="form-control" data-field="description" maxlength="100" value="{$d->description|escape}"><span class="np-wb__error" data-for="description"></span></label>

            {if $d->mode->value == 'address'}
                <div class="np-wb__group">{$btr->sviat__novaposhta__waybill_address_group|escape}</div>
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_address_note|escape}</span><input class="form-control" data-field="address_note" maxlength="50" value="{$d->addressNote|escape}"></label>
                <label class="np_check"><input type="checkbox" data-field="lifting" {if $d->lifting}checked{/if}> <span>{$btr->sviat__novaposhta__order_lifting|escape}</span></label>
                <div class="np-wb__grid np-wb__lifting" {if !$d->lifting}hidden{/if}>
                    <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__order_floor|escape}</span><input class="form-control" inputmode="numeric" data-field="floor" value="{$d->floor|escape}"><span class="np-wb__error" data-for="floor"></span></label>
                    <label class="np_check"><input type="checkbox" data-field="elevator" {if $d->elevator}checked{/if}> <span>{$btr->sviat__novaposhta__order_elevator|escape}</span></label>
                </div>
            {/if}

            <div class="np-wb__group">{$btr->sviat__novaposhta__waybill_payment_group|escape}</div>
            <div class="np-wb__grid">
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_payer|escape}</span><select class="form-control" data-field="payer_type">
                    <option value="Recipient" {if $d->payerType == 'Recipient'}selected{/if}>{$btr->sviat__novaposhta__waybill_recipient|escape}</option>
                    <option value="Sender" {if $d->payerType == 'Sender'}selected{/if}>{$btr->sviat__novaposhta__waybill_sender_party|escape}</option>
                </select></label>
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_payment|escape}</span><select class="form-control" data-field="payment_method">
                    <option value="Cash" {if $d->paymentMethod == 'Cash'}selected{/if}>{$btr->sviat__novaposhta__waybill_cash|escape}</option>
                    <option value="NonCash" {if $d->paymentMethod == 'NonCash'}selected{/if}>{$btr->sviat__novaposhta__waybill_noncash|escape}</option>
                </select></label>
            </div>
            <label class="np_check"><input type="checkbox" data-field="cod" {if $d->cod}checked{/if}> <span><b>{$btr->sviat__novaposhta__waybill_cod|escape}</b><br><span class="text_grey">{if $w.paid}{$btr->sviat__novaposhta__waybill_cod_paid|escape}{elseif $w.codPayment}{$btr->sviat__novaposhta__waybill_cod_auto|escape}{else}{$btr->sviat__novaposhta__waybill_cod_manual|escape}{/if}</span></span></label>
            <div class="np-wb__cod" {if !$d->cod}hidden{/if}>
                <div class="np-wb__grid">
                    <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_cod_amount|escape}</span><input class="form-control" inputmode="decimal" data-field="cod_amount" value="{$d->codAmount|escape}"><span class="np-wb__error" data-for="cod_amount"></span></label>
                    <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_cod_kind|escape}</span><select class="form-control" data-field="cod_kind">
                        <option value="money" {if $d->codKind->value == 'money'}selected{/if}>{$btr->sviat__novaposhta__waybill_cod_money|escape}</option>
                        <option value="control" {if $d->codKind->value == 'control'}selected{/if}>{$btr->sviat__novaposhta__waybill_cod_control|escape}</option>
                    </select><span class="np-wb__error" data-for="cod_kind"></span></label>
                </div>
                <label class="np-wb__field"><span class="heading_label">{$btr->sviat__novaposhta__waybill_cod_payer|escape}</span><select class="form-control" data-field="cod_payer">
                    <option value="Recipient" {if $d->codPayer == 'Recipient'}selected{/if}>{$btr->sviat__novaposhta__waybill_recipient|escape}</option>
                    <option value="Sender" {if $d->codPayer == 'Sender'}selected{/if}>{$btr->sviat__novaposhta__waybill_sender_party|escape}</option>
                </select></label>
            </div>

            <div class="np-wb__group">{$btr->sviat__novaposhta__waybill_dispatch_group|escape}</div>
            <div class="np-wb__line"><span class="text_600">{$btr->sviat__novaposhta__waybill_ship_date|escape}:</span> {$d->shipDate|escape}</div>

            <div class="np-wb__issues" role="alert"></div>
            <div class="np-wb__actions">
                <button type="button" class="btn btn_small btn_blue fn_np_wb_create">{$btr->sviat__novaposhta__waybill_create|escape}</button>
            </div>
            {np_wb_attach}
        </div>
    {/if}
</div>
{/if}

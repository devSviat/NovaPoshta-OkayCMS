{if $np_mass_report}
    <div class="row fn_np_mass_report">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="boxed np-mass">
                <div class="np-mass__head">
                    <div class="np-mass__title">{$btr->sviat__novaposhta__mass_heading|escape}: {$np_mass_report.title|escape}</div>
                    <button type="button" class="np-mass__hide fn_np_mass_hide">{$btr->sviat__novaposhta__mass_hide|escape}</button>
                </div>
                {foreach $np_mass_report.rows as $row}
                    <div class="np-mass__row np-mass__row--{$row.kind|escape}"><a href="{url controller=OrderAdmin id=$row.orderId return=$smarty.server.REQUEST_URI}">№ {$row.orderId|escape}</a> — {$row.text|escape}</div>
                {/foreach}
                {if $np_mass_report.untouched}
                    <div class="np-mass__note">{$np_mass_report.untouched|escape}</div>
                {/if}
            </div>
        </div>
    </div>
{/if}

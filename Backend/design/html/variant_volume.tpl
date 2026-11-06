{$np_volume = 0}
{if !empty($variant) && $variant->sviat__novaposhta__volume > 0}{$np_volume = $variant->sviat__novaposhta__volume + 0}{/if}
<div class="okay_list_boding variants_item_price">
    <div class="heading_label" title="{$btr->sviat__novaposhta__variant_volume_hint|escape}">{$btr->sviat__novaposhta__variant_volume|escape}</div>
    <input class="variant_input" name="variants[sviat__novaposhta__volume][]" type="text" inputmode="decimal" placeholder="0.012" value="{if $np_volume > 0}{$np_volume|escape}{/if}"/>
</div>

{* Product page, one variant: the volume in the variant's row, right after the weight (module.json modification). *}
{$np_volume = 0}
{if !empty($variant) && $variant->sviat__novaposhta__volume > 0}{$np_volume = $variant->sviat__novaposhta__volume + 0}{/if}
<div class="okay_list_boding variants_item_weight np_variant_volume">
    <div class="heading_label">{$btr->sviat__novaposhta__variant_volume|escape}
        <i class="fn_tooltips hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->sviat__novaposhta__variant_volume_hint|escape}" tabindex="0" role="img" aria-label="{$btr->sviat__novaposhta__variant_volume_hint|escape}">{include file='svg_icon.tpl' svgId='icon_tooltips'}</i>
    </div>
    <input class="variant_input" name="variants[sviat__novaposhta__volume][]" type="text" inputmode="decimal" placeholder="0.012" value="{if $np_volume > 0}{$np_volume|escape}{/if}"/>
</div>
{* Once per page: the core removes the new-variant row from the page on load, so the layout lives here. *}
{if empty($smarty.capture.np_volume_css)}{capture name=np_volume_css}1{/capture}
<style>
    .variants_list_item .np_variant_volume { flex: 0 0 104px; max-width: 104px; }
    .np_variant_volume .heading_label { white-space: nowrap; }
    /* The volume takes room from the name; while edited, the name opens over the row instead of squeezing it. */
    @media (min-width: 992px) {
        .variants_list_item .variants_item_name { flex: 0 1 180px; min-width: 0; max-width: none; position: relative; }
        .variants_list_item .variants_item_name:focus-within .variant_input { position: relative; z-index: 5; width: 560px; max-width: 60vw; box-shadow: 0 4px 14px rgba(0, 0, 0, .12); }
    }
</style>
{/if}

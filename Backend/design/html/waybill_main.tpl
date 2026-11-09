{* The order's waybill, once there is one: under the goods in the main column, as the old tracking module had it. *}
{if $np_waybill && $np_waybill.row}{include file=$smarty.current_dir|cat:'/waybill.tpl'}{/if}

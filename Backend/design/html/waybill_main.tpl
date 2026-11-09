{* Накладна замовлення, коли вона вже є: під товарами в основній колонці, як було в старому модулі трекінгу. *}
{if $np_waybill && $np_waybill.row}{include file=$smarty.current_dir|cat:'/waybill.tpl'}{/if}

{* Створення накладної (форма, перевірка, відмова): у правій колонці поруч із даними доставки замовлення. *}
{if $np_waybill && !$np_waybill.row}{include file=$smarty.current_dir|cat:'/waybill.tpl'}{/if}

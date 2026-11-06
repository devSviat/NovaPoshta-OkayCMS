<?php

$lang['sviat__novaposhta__title'] = 'Новая Почта — настройки';
$lang['sviat__novaposhta__key_box'] = 'Ключ API Новой Почты';
$lang['sviat__novaposhta__key_intro'] = 'Ключ нужен, чтобы модуль получал отделения и почтоматы, рассчитывал доставку и создавал ТТН. Его создают в бизнес-кабинете Новой Почты: Настройки → Безопасность → API-ключи.';
$lang['sviat__novaposhta__key_label'] = 'Ключ API';
$lang['sviat__novaposhta__key_placeholder_new'] = 'Вставьте ключ из бизнес-кабинета НП';
$lang['sviat__novaposhta__key_placeholder_saved'] = 'Вставьте новый ключ, чтобы заменить сохранённый';
$lang['sviat__novaposhta__key_saved_prefix'] = 'Сохранён ключ';
$lang['sviat__novaposhta__key_saved_suffix'] = 'Чтобы заменить — вставьте новый. Пустое поле ключ не меняет.';
$lang['sviat__novaposhta__save_check'] = 'Сохранить и проверить';
$lang['sviat__novaposhta__recheck'] = 'Проверить ещё раз';
$lang['sviat__novaposhta__checking'] = 'Проверяем…';
$lang['sviat__novaposhta__what_to_do'] = 'Что сделать:';
$lang['sviat__novaposhta__saved'] = 'Ключ сохранён.';
$lang['sviat__novaposhta__not_saved'] = 'Он не сохранён.';
$lang['sviat__novaposhta__not_saved_kept'] = 'Он не сохранён — остался прежний ключ.';

$lang['sviat__novaposhta__state_none_title'] = 'Ключ ещё не добавлен';
$lang['sviat__novaposhta__state_none_text'] = 'Без ключа модуль не показывает отделения в корзине и не создаёт ТТН.';
$lang['sviat__novaposhta__state_none_action'] = 'вставьте ключ из бизнес-кабинета НП и нажмите «Сохранить и проверить».';
$lang['sviat__novaposhta__state_unchecked_title'] = 'Ключ ещё не проверен';
$lang['sviat__novaposhta__state_unchecked_text'] = 'Модуль не знает, работает ли сохранённый ключ.';
$lang['sviat__novaposhta__state_unchecked_action'] = 'нажмите «Проверить ещё раз».';
$lang['sviat__novaposhta__state_ok_title'] = 'Ключ работает';
$lang['sviat__novaposhta__state_ok_text'] = 'Модуль имеет доступ ко всему, что ему нужно.';
$lang['sviat__novaposhta__state_invalid_title'] = 'Новая Почта не принимает этот ключ';
$lang['sviat__novaposhta__state_invalid_text'] = 'Ключ недействителен, просрочен или отозван.';
$lang['sviat__novaposhta__state_invalid_action'] = 'создайте новый ключ в бизнес-кабинете НП (Настройки → Безопасность → API-ключи) и вставьте его сюда.';
$lang['sviat__novaposhta__state_denied_title'] = 'Ключ без доступа к новому API НП';
$lang['sviat__novaposhta__state_denied_text'] = 'Без нового API не будут работать отделения в корзине, расчёт доставки и трекинг.';
$lang['sviat__novaposhta__state_denied_action'] = 'создайте в бизнес-кабинете НП новый ключ и вставьте его сюда. Если и новый без доступа — напишите в поддержку НП, что нужен доступ к API 1.0.';
$lang['sviat__novaposhta__state_unreachable_title'] = 'Не удалось связаться с Новой Почтой';
$lang['sviat__novaposhta__state_unreachable_text'] = 'Поэтому ключ не проверен.';
$lang['sviat__novaposhta__state_unreachable_action'] = 'нажмите «Проверить ещё раз» через несколько минут.';

$lang['sviat__novaposhta__api_new'] = 'Отделения, расчёт доставки, трекинг (новый API НП)';
$lang['sviat__novaposhta__api_old'] = 'Наложенный платёж, контроль оплаты, ТТН на адрес (старый API НП)';
$lang['sviat__novaposhta__access_ok'] = 'работает';
$lang['sviat__novaposhta__access_invalid_key'] = 'ключ не принят';
$lang['sviat__novaposhta__access_denied'] = 'нет доступа';
$lang['sviat__novaposhta__access_unreachable'] = 'не проверено';
$lang['sviat__novaposhta__checked_at'] = 'Проверено';

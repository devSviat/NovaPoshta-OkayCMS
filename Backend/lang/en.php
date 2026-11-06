<?php

$lang['sviat__novaposhta__title'] = 'Nova Poshta — settings';
$lang['sviat__novaposhta__key_box'] = 'Nova Poshta API key';
$lang['sviat__novaposhta__key_intro'] = 'The module needs the key to load branches and parcel lockers, calculate delivery and create waybills. Create it in the Nova Poshta business account: Settings → Security → API keys.';
$lang['sviat__novaposhta__key_label'] = 'API key';
$lang['sviat__novaposhta__key_placeholder_new'] = 'Paste the key from the Nova Poshta business account';
$lang['sviat__novaposhta__key_placeholder_saved'] = 'Paste a new key to replace the saved one';
$lang['sviat__novaposhta__key_saved_prefix'] = 'Saved key';
$lang['sviat__novaposhta__key_saved_suffix'] = 'To replace it, paste a new one. An empty field keeps the key.';
$lang['sviat__novaposhta__save_check'] = 'Save and check';
$lang['sviat__novaposhta__recheck'] = 'Check again';
$lang['sviat__novaposhta__checking'] = 'Checking…';
$lang['sviat__novaposhta__what_to_do'] = 'What to do:';
$lang['sviat__novaposhta__saved'] = 'The key is saved.';
$lang['sviat__novaposhta__not_saved'] = 'It was not saved.';
$lang['sviat__novaposhta__not_saved_kept'] = 'It was not saved — the previous key stays.';

$lang['sviat__novaposhta__state_none_title'] = 'No key yet';
$lang['sviat__novaposhta__state_none_text'] = 'Without a key the module shows no branches in the cart and creates no waybills.';
$lang['sviat__novaposhta__state_none_action'] = 'paste the key from the Nova Poshta business account and press “Save and check”.';
$lang['sviat__novaposhta__state_unchecked_title'] = 'The key has not been checked';
$lang['sviat__novaposhta__state_unchecked_text'] = 'The module does not know whether the saved key works.';
$lang['sviat__novaposhta__state_unchecked_action'] = 'press “Check again”.';
$lang['sviat__novaposhta__state_ok_title'] = 'The key works';
$lang['sviat__novaposhta__state_ok_text'] = 'The module has access to everything it needs.';
$lang['sviat__novaposhta__state_invalid_title'] = 'Nova Poshta does not accept this key';
$lang['sviat__novaposhta__state_invalid_text'] = 'The key is invalid, expired or revoked.';
$lang['sviat__novaposhta__state_invalid_action'] = 'create a new key in the Nova Poshta business account (Settings → Security → API keys) and paste it here.';
$lang['sviat__novaposhta__state_denied_title'] = 'The key has no access to the new Nova Poshta API';
$lang['sviat__novaposhta__state_denied_text'] = 'Without the new API, branches in the cart, delivery calculation and tracking will not work.';
$lang['sviat__novaposhta__state_denied_action'] = 'create a new key in the Nova Poshta business account and paste it here. If the new key has no access either, ask Nova Poshta support for access to API 1.0.';
$lang['sviat__novaposhta__state_unreachable_title'] = 'Could not reach Nova Poshta';
$lang['sviat__novaposhta__state_unreachable_text'] = 'So the key was not checked.';
$lang['sviat__novaposhta__state_unreachable_action'] = 'press “Check again” in a few minutes.';

$lang['sviat__novaposhta__api_new'] = 'Branches, delivery calculation, tracking (new NP API)';
$lang['sviat__novaposhta__api_old'] = 'Cash on delivery, payment control, address waybills (old NP API)';
$lang['sviat__novaposhta__access_ok'] = 'works';
$lang['sviat__novaposhta__access_invalid_key'] = 'key rejected';
$lang['sviat__novaposhta__access_denied'] = 'no access';
$lang['sviat__novaposhta__access_unreachable'] = 'not checked';
$lang['sviat__novaposhta__checked_at'] = 'Checked';

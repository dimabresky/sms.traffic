<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

global $APPLICATION;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$mid = 'smstraffic';
$MODULE_RIGHT = $APPLICATION->GetGroupRight($mid);
if ($MODULE_RIGHT < 'R') {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && $MODULE_RIGHT >= 'W'
    && !empty($_REQUEST['Update'])
    && check_bitrix_sessid()
) {
    Option::set($mid, 'login', (string)($_REQUEST['login'] ?? ''));
    Option::set($mid, 'password', (string)($_REQUEST['password'] ?? ''));
    Option::set($mid, 'originators', (string)($_REQUEST['originators'] ?? ''));
    Option::set($mid, 'rus', (string)($_REQUEST['rus'] ?? '5'));
    Option::set($mid, 'route', (string)($_REQUEST['route'] ?? ''));
    Option::set($mid, 'route_group_id', (string)($_REQUEST['route_group_id'] ?? ''));
    Option::set($mid, 'api_base_primary', (string)($_REQUEST['api_base_primary'] ?? ''));
    Option::set($mid, 'api_base_secondary', (string)($_REQUEST['api_base_secondary'] ?? ''));
}

$login = Option::get($mid, 'login', '');
$password = Option::get($mid, 'password', '');
$originators = Option::get($mid, 'originators', '');
$rus = Option::get($mid, 'rus', '5');
$route = Option::get($mid, 'route', '');
$routeGroupId = Option::get($mid, 'route_group_id', '');
$apiPrimary = Option::get($mid, 'api_base_primary', 'https://sds.smstraffic.by/smartdelivery-in');
$apiSecondary = Option::get($mid, 'api_base_secondary', 'https://sds2.smstraffic.by/smartdelivery-in');

$APPLICATION->SetTitle(Loc::getMessage('SMSTRAFFIC_OPT_TITLE'));
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$tabs = [
    [
        'DIV' => 'smstraffic_main',
        'TAB' => Loc::getMessage('SMSTRAFFIC_OPT_TAB_MAIN'),
        'TITLE' => Loc::getMessage('SMSTRAFFIC_OPT_TAB_MAIN'),
    ],
];
$tabControl = new CAdminTabControl('smstrafficTab', $tabs);
$tabControl->Begin();
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($mid) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <?php $tabControl->BeginNextTab(); ?>
    <tr>
        <td colspan="2"><?= Loc::getMessage('SMSTRAFFIC_OPT_MAIN_MODULE_LINK') ?></td>
    </tr>
    <tr>
        <td width="40%"><?= Loc::getMessage('SMSTRAFFIC_OPT_LOGIN') ?>:</td>
        <td><input type="text" name="login" value="<?= htmlspecialcharsbx($login) ?>" size="40"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMSTRAFFIC_OPT_PASSWORD') ?>:</td>
        <td><input type="password" name="password" value="<?= htmlspecialcharsbx($password) ?>" size="40" autocomplete="new-password"></td>
    </tr>
    <tr>
        <td class="adm-detail-valign-top"><?= Loc::getMessage('SMSTRAFFIC_OPT_ORIGINATORS') ?>:</td>
        <td>
            <textarea name="originators" rows="4" cols="50"><?= htmlspecialcharsbx($originators) ?></textarea>
            <div class="adm-info-message-wrap"><div class="adm-info-message"><?= Loc::getMessage('SMSTRAFFIC_OPT_ORIGINATORS_HINT') ?></div></div>
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMSTRAFFIC_OPT_RUS') ?>:</td>
        <td>
            <select name="rus">
                <option value="5"<?= $rus === '5' ? ' selected' : '' ?>>5 (кириллица / Unicode)</option>
                <option value="1"<?= $rus === '1' ? ' selected' : '' ?>>1</option>
                <option value="0"<?= $rus === '0' ? ' selected' : '' ?>>0 (транслит)</option>
            </select>
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMSTRAFFIC_OPT_ROUTE') ?>:</td>
        <td><input type="text" name="route" value="<?= htmlspecialcharsbx($route) ?>" size="50" placeholder="viber(60)-sms"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMSTRAFFIC_OPT_ROUTE_GROUP') ?>:</td>
        <td><input type="text" name="route_group_id" value="<?= htmlspecialcharsbx($routeGroupId) ?>" size="20"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMSTRAFFIC_OPT_PRIMARY') ?>:</td>
        <td><input type="text" name="api_base_primary" value="<?= htmlspecialcharsbx($apiPrimary) ?>" size="60"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMSTRAFFIC_OPT_SECONDARY') ?>:</td>
        <td><input type="text" name="api_base_secondary" value="<?= htmlspecialcharsbx($apiSecondary) ?>" size="60"></td>
    </tr>
    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="Update" value="<?= Loc::getMessage('SMSTRAFFIC_OPT_SAVE') ?>" class="adm-btn-save">
    <?php $tabControl->End(); ?>
</form>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

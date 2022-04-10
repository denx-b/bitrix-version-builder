<?php

/**
 * @global CUser $USER
 * @global CMain $APPLICATION
 * @global $Update
 * @global $Apply
 * @global $RestoreDefaults
 * @global $mid
 */

// Проверка прав
$modulePerms = $APPLICATION->GetGroupRight($mid);
if ($modulePerms < 'R') {
    return;
}

\Bitrix\Main\Localization\Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/options.php');
\Bitrix\Main\Localization\Loc::loadMessages(__FILE__);

// Получение конфигурации табов и опций
$tabsConf = require __DIR__ . '/options_conf.php';

// Создаём массив табов для создания экземпляра объекта
$arTabs = $arAllOptions = [];
foreach ($tabsConf as $key => $arTab) {
    $arTabs[] = ['DIV' => $key, 'TAB' => $arTab['TAB_NAME'], 'TITLE' => $arTab['TAB_TITLE'], 'ICON' => $arTab['ICON']];
    $arAllOptions += $arTab['options'];
}

// Объект табов
$tabControl = new CAdminTabControl('tabControl', $arTabs);

// Сохранение значений
$request = \Bitrix\Main\Context::getCurrent()->getRequest();
if($request->isPost() && $Update.$Apply.$RestoreDefaults <> '' && $modulePerms === 'W' && check_bitrix_sessid())
{
    if (strlen($RestoreDefaults) > 0) {
        \Bitrix\Main\Config\Option::delete($mid);
    } else {
        foreach ($arAllOptions as $arOption) {
            $name = $arOption['name'];
            $val = $request->get($name);
            if ($val === null) {
                continue;
            }
            if ($arOption['type'] === 'checkbox' && $val !== 'Y') {
                $val = 'N';
            }
            \Bitrix\Main\Config\Option::set($mid, $name, $val);
        }
    }
    if (strlen($Update) > 0 && strlen($request->get('back_url_settings')) > 0) {
        LocalRedirect($request->get('back_url_settings'));
    } else {
        LocalRedirect($APPLICATION->GetCurPage() . '?mid=' . urlencode($mid) . '&lang=' . urlencode(LANGUAGE_ID) . '&back_url_settings=' . urlencode($request->get('back_url_settings')) . '&' . $tabControl->ActiveTabParam());
    }
}

/*
 * Вывод интерфейса опций
 */
$tabControl->Begin();
?>
<form method="post" action="<?=$APPLICATION->GetCurPage()?>?mid=<?=urlencode($mid)?>&lang=<?=LANGUAGE_ID?>">
    <?php
    foreach ($tabsConf as $arTab) {
        if (!array_key_exists('options', $arTab) || !is_array($arTab['options'])) {
            continue;
        }
        $tabControl->BeginNextTab();
        foreach ($arTab['options'] as $arOption) {
            if ($arOption['type'] === 'heading') {
                ?><tr class="heading"><td colspan="2"><?=$arOption['heading']?></td></tr><?php
            } elseif ($arOption['type'] === 'message') {
                ?><tr><td colspan="2" align="center"><div class="adm-info-message-wrap" align="center"><div class="adm-info-message"><?=$arOption['message']?></div></div></td></tr><?php
            } else {
                $val = \Bitrix\Main\Config\Option::get($mid, $arOption['name'], $arOption['value']);
                ?>
                <tr>
                    <td width="50%" class="adm-detail-content-cell-l" nowrap<?= $arOption['type'] === 'textarea' ? ' class="adm-detail-valign-top"' : '' ?>>
                        <label for="<?= $arOption['name']; ?>"><?= $arOption['title']; ?>:</label>
                    <td width="50%" class="adm-detail-content-cell-r">
                        <?php
                        switch ($arOption['type'])
                        {
                            case 'checkbox':
                                ?><input type="hidden" name="<?= $arOption['name']; ?>" value="N">
                                <input type="checkbox" id="<?= $arOption['name']; ?>" name="<?= $arOption['name']; ?>" value="Y"<?= ($val === 'Y' ? ' checked' : ''); ?>><?php
                                break;
                            case 'text':
                                ?><input type="text" id="<?= $arOption['name']; ?>" name="<?= $arOption['name']; ?>" value="<?= htmlspecialcharsbx($val); ?>" size="<?=$arOption['size']?:44?>" maxlength="255"><?php
                                break;
                            case 'textarea':
                                ?><textarea id="<?= $arOption['name']; ?>" name="<?= $arOption['name']; ?>" cols="<?=$arOption['cols']?:40?>" rows="<?=$arOption['rows']?:4?>"><?= htmlspecialcharsbx($val); ?></textarea><?php
                                break;
                            case 'list':
                                ?>
                                <select id="<?=$arOption['name']?>" name="<?=$arOption['name']?>">
                                    <?php foreach ($arOption['list'] as $listValue => $listTitle):?>
                                    <option value="<?=$listValue?>"<?=$listValue==$val?' selected':''?>><?=$listTitle?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php
                                break;
                        }
                        ?>
                    </td>
                </tr>
                <?php
            }
        }
        $tabControl->EndTab();
    }
    ?>
    <?php $tabControl->Buttons();?>
    <input type="submit" name="Update" <?= $modulePerms < 'W' ? 'disabled' : '' ?> value="<?=GetMessage('MAIN_SAVE')?>" title="<?=GetMessage('MAIN_OPT_SAVE_TITLE')?>" class="adm-btn-save">
    <input type="submit" name="Apply" value="<?=GetMessage('MAIN_OPT_APPLY')?>" title="<?=GetMessage('MAIN_OPT_APPLY_TITLE')?>">
    <input type="submit" name="RestoreDefaults" <?= $modulePerms < 'W' ? 'disabled' : '' ?> title="<?=GetMessage('MAIN_HINT_RESTORE_DEFAULTS')?>" OnClick="return confirm('<?=AddSlashes(GetMessage('MAIN_HINT_RESTORE_DEFAULTS_WARNING'))?>')" value="<?=GetMessage('MAIN_RESTORE_DEFAULTS')?>">
    <?=bitrix_sessid_post();?>
    <?php $tabControl->End();?>
</form>

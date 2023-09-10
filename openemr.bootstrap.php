<?php

/**
 * Bootstrap custom Fax module.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    Stephen Nielson <stephen@nielson.org>
 * @copyright Copyright (c) 2019 Stephen Nielson <stephen@nielson.org>
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2022 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Events\Globals\GlobalsInitializedEvent;
use OpenEMR\Events\Messaging\SendSmsEvent;
use OpenEMR\Events\PatientDocuments\PatientDocumentEvent;
use OpenEMR\Events\PatientReport\PatientReportEvent;
use OpenEMR\Menu\MenuEvent;
use OpenEMR\Services\Globals\GlobalSetting;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @global OpenEMR\Core\ModulesClassLoader $classLoader
 */
$classLoader->registerNamespaceIfNotExists('OpenEMR\\Modules\\FaxSMS\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');

$allowFax = ($GLOBALS['oefax_enable_fax'] ?? null);
$allowSMS = ($GLOBALS['oefax_enable_sms'] ?? null);
$allowSMSButtons = ($GLOBALS['oesms_send'] ?? null);

function oe_module_faxsms_add_menu_item(MenuEvent $event): MenuEvent
{
    $allowFax = ($GLOBALS['oefax_enable_fax'] ?? null);
    $allowSMS = ($GLOBALS['oefax_enable_sms'] ?? null);
    $menu = $event->getMenu();

    $menuItem = new stdClass();
    $menuItem->requirement = 0;
    $menuItem->target = 'sms';
    $menuItem->menu_id = 'mod0';
    $menuItem->label = xlt("SMS Module");
    $menuItem->url = "/interface/modules/custom_modules/oe-module-faxsms/messageUI.php?type=sms";
    $menuItem->children = [];
    $menuItem->acl_req = ["patients", "docs"];
    $menuItem->global_req = ["oefax_enable_sms"];

    $menuItem2 = new stdClass();
    $menuItem2->requirement = 0;
    $menuItem2->target = 'fax';
    $menuItem2->menu_id = 'mod1';
    $menuItem2->label = ($allowFax == '3' ? xlt("etherFAX Tools") : xlt("Faxing"));
    $menuItem2->url = "/interface/modules/custom_modules/oe-module-faxsms/messageUI.php?type=fax";
    $menuItem2->children = [];
    $menuItem2->acl_req = ["patients", "docs"];
    $menuItem2->global_req = ["oefax_enable_fax"];
    foreach ($menu as $item) {
        if ($item->menu_id == 'modimg') {
            if (!empty($allowSMS)) {
                $item->children[] = $menuItem;
            }
            if (!empty($allowFax)) {
                $item->children[] = $menuItem2;
            }
            break;
        }
    }
    $event->setMenu($menu);
    return $event;
}

/**
 * @var EventDispatcherInterface $eventDispatcher
 * @var array                    $module
 * @global                       $eventDispatcher @see ModulesApplication::loadCustomModule
 * @global                       $module          @see ModulesApplication::loadCustomModule
 */

function createFaxModuleGlobals(GlobalsInitializedEvent $event): void
{
    $select_array = array(0 => xl('Disabled'), 1 => xl('RingCentral SMS'), 2 => xl('Twilio SMS'));
    $select_array_fax = array(0 => xl('Disabled'), 1 => xl('RingCentral Fax'), 3 => xl('etherFAX'));
    $instruct = xl('Enable Fax SMS Support. Remember to setup credentials.');

    $event->getGlobalsService()->createSection("Modules", "Report");
    $setting = new GlobalSetting(xl('Enable SMS Module'), $select_array, 0, xl('Enable SMS Support. Remember to setup credentials.'));
    $event->getGlobalsService()->appendToSection("Modules", "oefax_enable_sms", $setting);
    $setting = new GlobalSetting(xl('Enable Fax Module'), $select_array_fax, 0, xl('Enable Fax Support. Remember to setup credentials.'));
    $event->getGlobalsService()->appendToSection("Modules", "oefax_enable_fax", $setting);

    $instruct = xl('Enable Send SMS Dialog Support. Various opportunities in UI.');
    $setting = new GlobalSetting(xl('Enable Send SMS Dialog'), 'bool', 0, $instruct);
    $event->getGlobalsService()->appendToSection("Modules", "oesms_send", $setting);

    $instruct = xl('Restrict Users to their own account credentials. Usage accounting is tagged by user.');
    $setting = new GlobalSetting(xl('Individual User Accounts'), 'bool', 1, $instruct);
    $event->getGlobalsService()->appendToSection("Modules", "oesms_send", $setting);
}

$eventDispatcher->addListener(MenuEvent::MENU_UPDATE, 'oe_module_faxsms_add_menu_item');
$eventDispatcher->addListener(GlobalsInitializedEvent::EVENT_HANDLE, 'createFaxModuleGlobals');

// patient report send fax button
function oe_module_faxsms_patient_report_render_action_buttons(Event $event): void
{
    ?>
    <button type="button" class="genfax btn btn-success btn-sm btn-send-msg" value="<?php echo xla('Send Fax'); ?>"><?php echo xlt('Send Fax'); ?></button><span id="waitplace"></span>
    <input type='hidden' name='fax' value='0'>
    <?php
}

function oe_module_faxsms_patient_report_render_javascript_post_load(Event $event): void
{
    ?>
    function getFaxContent() {
    top.restoreSession();
    document.report_form.fax.value = 1;
    let url = 'custom_report.php';
    let wait = '<span id="wait"><?php echo '  ' . xlt("Building Document") . ' ... '; ?><i class="fa fa-cog fa-spin fa-2x"></i></span>';
    $("#waitplace").append(wait);
    $.ajax({
    type: "POST",
    url: url,
    data: $("#report_form").serialize(),
    success: function (content) {
    document.report_form.fax.value = 0;
    let btnClose = <?php echo xlj("Cancel"); ?>;
    let title = <?php echo xlj("Send To Contact"); ?>;
    let url = top.webroot_url + '/interface/modules/custom_modules/oe-module-faxsms/contact.php?isContent=0&type=fax&file=' + content;
    dlgopen(url, '', 'modal-sm', 700, '', title, {buttons: [{text: btnClose, close: true, style: 'secondary'}]});
    return false;
    }
    }).always(function () {
    $("#wait").remove();
    });
    return false;
    }
    $(".genfax").click(function() {getFaxContent();});
    <?php
}

if ($allowFax) {
    $eventDispatcher->addListener(PatientReportEvent::ACTIONS_RENDER_POST, 'oe_module_faxsms_patient_report_render_action_buttons');
    $eventDispatcher->addListener(PatientReportEvent::JAVASCRIPT_READY_POST, 'oe_module_faxsms_patient_report_render_javascript_post_load');
}
// patient documents fax anchor
function oe_module_faxsms_document_render_action_anchors(Event $event)
{
    ?>
    <a class="btn btn-success btn-sm btn-send-msg" href="" onclick="return doFax(event,file,mime)"><span><?php echo xlt('Send Fax'); ?></span></a>
    <?php
}

function oe_module_faxsms_document_render_javascript_fax_dialog(Event $event)
{
    ?>
    function doFax(e, filePath, mime='') {
    e.preventDefault();
    let btnClose = <?php echo xlj("Cancel"); ?>;
    let title = <?php echo xlj("Send To Contact"); ?>;
    let url = top.webroot_url +
    '/interface/modules/custom_modules/oe-module-faxsms/contact.php?isDocuments=true&type=fax&file=' + filePath +
    '&mime=' + mime;
    dlgopen(url, 'faxto', 'modal-md', 700, '', title, {buttons: [{text: btnClose, close: true, style: 'primary'}]});
    return false;
    }
    <?php
}

if ($allowFax) {
    $eventDispatcher->addListener(PatientDocumentEvent::ACTIONS_RENDER_FAX_ANCHOR, 'oe_module_faxsms_document_render_action_anchors');
    $eventDispatcher->addListener(PatientDocumentEvent::JAVASCRIPT_READY_FAX_DIALOG, 'oe_module_faxsms_document_render_javascript_fax_dialog');
}
// send sms button
function oe_module_faxsms_sms_render_action_buttons(Event $event): void
{
    if (!$event->smsAuthorised) {
        return;
    }
    ?>
    <button type="button" class="sendsms btn btn-success btn-sm btn-send-msg"
        onclick="sendSMS(<?php echo attr_js($event->pid) ?>, <?php echo attr_js($event->title) ?>, <?php echo attr_js($event->getPatientDetails(null, true)) ?>);" value="true"><?php echo xlt('Notify'); ?></button>
    <?php
}

function oe_module_faxsms_sms_render_javascript_post_load(Event $event): void
{
    ?>
    function sendSMS(pid, docName, details) {
    let btnClose = <?php echo xlj("Cancel"); ?>;
    let title = <?php echo xlj("Send SMS Message"); ?>;
    let url = top.webroot_url + '/interface/modules/custom_modules/oe-module-faxsms/contact.php?isSMS=1&pid=' +
    encodeURIComponent(pid) +
    '&title=' + encodeURIComponent(docName) +
    '&details=' + encodeURIComponent(details);
    dlgopen(url, '', 'modal-sm', 700, '', title, {
    buttons: [{text: btnClose, close: true, style: 'secondary'}]
    });
    }
    <?php
}

if ($allowSMSButtons) {
    $eventDispatcher->addListener(SendSmsEvent::ACTIONS_RENDER_SMS_POST, 'oe_module_faxsms_sms_render_action_buttons');
    $eventDispatcher->addListener(SendSmsEvent::JAVASCRIPT_READY_SMS_POST, 'oe_module_faxsms_sms_render_javascript_post_load');
}
/* example for sms send */
/*
<?php
// setup
use OpenEMR\Events\Messaging\SendSmsEvent;
$oesms = !empty($GLOBALS['oefax_enable']) && !empty($GLOBALS['oesms_send']) ? 1 : 0;
$smsSendDispatcher = $GLOBALS['kernel']->getEventDispatcher();
//button
if ($oesms) {
    $smsSendDispatcher->dispatch(SendSmsEvent::ACTIONS_RENDER_SMS_POST, new GenericEvent());
}
// function
if ($oesms) {
    $smsSendDispatcher->dispatch(SendSmsEvent::JAVASCRIPT_READY_SMS_POST, new GenericEvent());
}
?>
*/

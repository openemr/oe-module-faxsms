<?php
/**
 * Fax SMS Module Member
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2018-2020 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$sessionAllowWrite = true;
require_once(__DIR__ . "/../../../globals.php");

use OpenEMR\Core\Header;
use OpenEMR\Modules\FaxSMS\Controllers\AppDispatch;

$clientApp = AppDispatch::getApiService();
$service = $clientApp::getServiceType();
$title = $service == "1" ? 'RingCentral' : 'Twilio';

$logged_in = $clientApp->authenticate();
if (empty($logged_in) && $service == "1") {
    $request_url = $clientApp->getLogIn();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Fax Module'); ?></title>
    <link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/dropzone/dist/dropzone.css">
    <?php
    Header::setupHeader(['opener', 'datetime-picker']);
    echo "<script>var pid=" . js_escape($pid) . ";var portalUrl=" . js_escape($clientApp->portalUrl) .
        ";const currentService=" . js_escape($service) . ";</script>";
    ?>
    <script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/dropzone/dist/dropzone.js"></script>
    <script>
        const queueMsg = '' + <?php echo xlj('Fax Queue. Drop files or Click here to Fax.') ?>;
        Dropzone.autoDiscover = false;
        $(function () {
            fileTypes = '';
            if (currentService == '2') {
                fileTypes = 'application/pdf';
            }
            var faxQueue = new Dropzone("#faxQueue", {
                paramName: 'fax',
                url: 'faxProcessUploads',
                dictDefaultMessage: queueMsg,
                clickable: true,
                enqueueForUpload: true,
                maxFilesize: 25,
                acceptedFiles: fileTypes,
                uploadMultiple: false,
                addRemoveLinks: true,
                init: function (e) {
                    let ofile = '';
                    this.on("addedfile", function (file) {
                        console.log('new file added ', file);
                        ofile = file;
                    });
                    this.on("sending", function (file) {
                        console.log('upload started ', file);
                        $('.meter').show();
                    });
                    this.on("success", function (file, response) {
                        let thisFile = response;
                        console.log('upload success ', thisFile);
                        sendFax(thisFile, 'queue');
                    });
                    this.on("queuecomplete", function (progress) {
                        $('.meter').delay(999).slideUp(999);
                    });
                    this.on("removedfile", function (file) {
                        console.log(file);
                    });
                }
            });
        });
        $(function () {
            $('.datepicker').datetimepicker({
                <?php
                $datetimepicker_timepicker = false;
                $datetimepicker_showseconds = false;
                $datetimepicker_formatInput = false;
                require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php');
                ?>
            });
            var dateRange = new Date(new Date().setDate(new Date().getDate() - 7));
            $("#fromdate").val(dateRange.toJSON().slice(0, 10));
            $("#todate").val(new Date().toJSON().slice(0, 10));
            if (currentService === '2') {
                $(".ringcentral").hide();
            } else {
                $(".twilio").hide();
            }
            // populate
            retrieveMsgs();
            $('#received').tab('show');
        });

        var sendFax = function (filePath, from = '') {
            let btnClose = <?php echo xlj("Cancel"); ?>;
            let title = <?php echo xlj("Send To Contact"); ?>;
            let url = top.webroot_url + '/interface/modules/custom_modules/oe-module-faxsms/contact.php?isDocuments=false&isQueue=' +
                encodeURIComponent(from) + '&file=' + filePath; // do not encode filePath
            // leave dialog name param empty so send dialogs can cascade.
            dlgopen(url, '', 'modal-md', 650, '', title, { // dialog restores session
                buttons: [
                    {text: btnClose, close: true, style: 'secondary btn-sm'}
                ]
            });
        };

        var docInfo = function (e, ppath) {
            top.restoreSession();
            let msg = <?php echo xlj('Your Account Portal') ?>;
            dlgopen(ppath, '_blank', 1240, 900, true, msg)
        };

        var popNotify = function (e, ppath) {
            top.restoreSession();
            let msg = <?php echo xlj('Are you sure you wish to send all scheduled reminders now.') ?>;
            if (e === 'live') {
                let yn = confirm(msg);
                if (!yn) return false;
            }
            let msg1 = <?php echo xlj('Appointment Reminder Alerts') ?>;
            dlgopen(ppath, '_blank', 1240, 900, true, msg1)
        };

        var doSetup = function (e) {
            top.restoreSession();
            e.preventDefault();
            let msg = <?php echo xlj('Credentials and SMS Notifications') ?>;
            dlgopen('', 'setup', 'modal-md', 500, '', msg, {
                buttons: [
                    {text: 'Cancel', close: true, style: 'secondary  btn-sm'}
                ],
                url: 'setup.php'
            });
        };

        function base64ToArrayBuffer(_base64Str) {
            var binaryString = window.atob(_base64Str);
            var binaryLen = binaryString.length;
            var bytes = new Uint8Array(binaryLen);
            for (var i = 0; i < binaryLen; i++) {
                var ascii = binaryString.charCodeAt(i);
                bytes[i] = ascii;
            }
            return bytes;
        }

        function showDocument(_base64Str, _contentType = 'text/plain') {
            var byte = base64ToArrayBuffer(_base64Str);
            var blob = new Blob([byte], { type: _contentType });
            window.open(URL.createObjectURL(blob), "_blank");
        }

        // For use with window cascade popup Twilio
        function viewDocument(e = '', docuri) {
            //top.restoreSession();
            if (e) {
                e.preventDefault();
            }
            let width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ?
                document.documentElement.clientWidth : screen.width;
            let height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ?
                document.documentElement.clientHeight : screen.height;
            height = screen.height ? screen.height * 0.95 : height;
            let left = (width/4);
            let top = '10';
            let win = window.open('', '', 'toolbar=0, location=0, directories=0, status=0, menubar=0, scrollbars=0, resizable=0, copyhistory=0, width='+width/1.75+', height='+height+', top='+top+', left='+left);
            //let win = cascwin('', '', width / 2, (height), "resizable=1,scrollbars=0,location=0,toolbar=0");
            win.document.write("<iframe width='100%' height='100%' style='border:none;' src='"+docuri+"'><\/iframe>");
        }

        function getDocument(e, docuri, docid, downFlag) {
            top.restoreSession();
            e.preventDefault();
            let actionUrl = 'viewFax';
            $("#brand").addClass('fa fa-spinner fa-spin');
            return $.post(actionUrl, {
                'docuri': docuri,
                'docid': docid,
                'pid': pid,
                'download': downFlag
            }).done(function (data) {
                $("#brand").removeClass('fa fa-spinner fa-spin');
                if (downFlag === 'true') {
                    location.href = "disposeDoc";
                    return false;
                }
                viewDocument('', data);
            });
        }

        // Fax and SMS status
        function retrieveMsgs(e = '', req = '') {
            top.restoreSession();
            if (e) {
                e.preventDefault();
            }
            let actionUrl = 'getPending';
            let id = pid;
            let datefrom = $('#fromdate').val();
            let dateto = $('#todate').val();
            let data = [];
            $("#brand").addClass('fa fa-spinner fa-spin');
            $("#rcvdetails tbody").empty();
            $("#sentdetails tbody").empty();
            $("#msgdetails tbody").empty();
            let Service = currentService;
            return $.post(actionUrl,
                {
                    'pid': pid,
                    'datefrom': datefrom,
                    'dateto': dateto
                }, function () {}, 'json').done(function (data) {
                if (data.error) {
                    $("#brand").removeClass('fa fa-spinner fa-spin');
                    if (Service === '1') {
                        $("#loginButton").removeClass("d-none");
                    }
                    alertMsg(data.error);
                    return false;
                }
                // populate our panels
                $("#rcvdetails tbody").empty().append(data[0]);
                $("#sentdetails tbody").empty().append(data[1]);
                $("#msgdetails tbody").empty().append(data[2]);
                // get call logs
                getLogs();
            }).fail(function (xhr, status, error) {
                alertMsg(<?php echo xlj('Not Authenticated. Restart from Modules menu or ensure credentials are setup from Activity menu.') ?>, 5000);
                if (Service === '1') {
                    $("#loginButton").removeClass("d-none");
                }
            }).always(function () {
                $("#brand").removeClass('fa fa-spinner fa-spin');
            });
        }

        // Our Call Logs.
        function getLogs() {
            top.restoreSession();
            let actionUrl = 'getCallLogs';
            let id = pid;
            let datefrom = $('#fromdate').val();
            let dateto = $('#todate').val();

            $("#brand").addClass('fa fa-spinner fa-spin');
            return $.post(actionUrl, {
                'pid': pid,
                'datefrom': datefrom,
                'dateto': dateto
            }).done(function (data) {
                var err = (data.search(/Exception/) !== -1 ? 1 : 0);
                if (!err) {
                    err = (data.search(/Error:/) !== -1 ? 1 : 0);
                }
                if (err) {
                    alertMsg(data);
                }
                $("#logdetails tbody").empty().append(data);

                // Get SMS appointments notifications
                getNotificationLog();
            }).always(function () {
                $("#brand").removeClass('fa fa-spinner fa-spin');
            });
        }

        function getNotificationLog() {
            top.restoreSession();
            let actionUrl = 'getNotificationLog';
            let id = pid;
            let datefrom = $('#fromdate').val() + " 00:00:00";
            let dateto = $('#todate').val() + " 23:59:59";

            $("#brand").addClass('fa fa-spinner fa-spin');
            return $.post(actionUrl, {
                'pid': pid,
                'datefrom': datefrom,
                'dateto': dateto
            }).done(function (data) {
                var err = (data.search(/Exception/) !== -1 ? 1 : 0);
                if (!err) {
                    err = (data.search(/Error:/) !== -1 ? 1 : 0);
                }
                if (err) {
                    alertMsg(data);
                }
                $("#alertdetails tbody").empty().append(data);
            }).always(function () {
                $("#brand").removeClass('fa fa-spinner fa-spin');
            });
        }

        function getSelResource() {
            return $('#resource option:selected').val();
        }

        function logIn() {
            top.restoreSession();
            return $.post('getLogIn', {
            })
            /*dlgopen('rcauth.php', '', 'modal-md', 500, true, '', {
                type: 'ajax',
                url: 'rcauth.php'
            });*/
        }

        function messageShow(id) {
            $("."+id).toggleClass("d-none");
        }

        function messageReply(phone) {
            let btnClose = <?php echo xlj("Cancel"); ?>;
            let title = <?php echo xlj("Message Reply"); ?>;
            let url = top.webroot_url + '/interface/modules/custom_modules/oe-module-faxsms/contact.php?isSMS=1&recipient=' +
                encodeURIComponent(phone);
            // leave dialog name param empty so send dialogs can cascade.
            dlgopen(url, '', 'modal-md', 600, '', title, {
                buttons: [
                    {text: btnClose, close: true, style: 'secondary btn-sm'}
                ]
            });
        }
    </script>
</head>
<body class="body_top">
    <div class="sticky-top">
    <nav class="navbar navbar-expand-xl navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="#">
                <?php echo "Fax SMS ($title)"; ?>
            </a>
            <button type="button" class="bg-primary navbar-toggler mr-auto" data-toggle="collapse" data-target="#nav-header-collapse">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="nav-header-collapse">
                <form class="navbar-form navbar-left form-inline" method="GET" role="search">
                    <div class="form-group">
                        <label class="mx-1" for="formdate"><?php echo xlt('Activities From Date') ?>:</label>
                        <input type="text" id="fromdate" name="fromdate" class="form-control input-sm datepicker" placeholder="YYYY-MM-DD" value=''>
                    </div>
                    <div class="form-group">
                        <label class="mx-1" for="todate"><?php echo xlt('To Date') ?>:</label>
                        <input type="text" id="todate" name="todate" class="form-control input-sm datepicker" placeholder="YYYY-MM-DD" value=''>
                    </div>
                    <div class="form-group">
                        <button type="button" class="btn btn-primary btn-search" onclick="retrieveMsgs(event,this)" title="<?php echo xla('Click to get current history.') ?>"></button>
                    </div>
                    <!-- manual login oauth2 RC -->
                    <div class="form-group ringcentral">
                        <button id="loginButton" onclick="location.reload()" class="btn btn-danger d-none">Log In<i class="fa fa-sign-in-alt ml-2" ></i></button>
                    </div>
                </form>
                <div class="nav-item dropdown ml-auto">
                    <button class="btn btn-primary dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
                        <?php echo xlt('Actions'); ?><span class="caret"></span>
                    </button>
                    <div class="dropdown-menu" role="menu">
                        <a class="dropdown-item" href="#" onclick="doSetup(event)"><?php echo xlt('Account Credentials'); ?></a>
                        <a class="dropdown-item" href="#" onclick="popNotify('', './rc_sms_notification.php?dryrun=1&site=<?php echo $_SESSION['site_id'] ?>')"><?php echo xlt('Test SMS Reminders'); ?></a>
                        <a class="dropdown-item" href="#" onclick="popNotify('live', './rc_sms_notification.php?site=<?php echo $_SESSION['site_id'] ?>')"><?php echo xlt('Send SMS Reminders'); ?></a>
                        <a class="dropdown-item ringcentral" href="#" onclick="docInfo(event, portalUrl)"><?php echo xlt('Portal Gateway'); ?></a>
                    </div>
                    <button type="button" class="nav-item ringcentral btn btn-secondary btn-transmit" onclick="docInfo(event, portalUrl)"><?php echo xlt('Account Portal'); ?>
                    </button>
                </div>
            </div><!-- /.navbar-collapse -->
    </nav>
    </div>
    <div class="container-fluid main-container mt-3">
        <div class="row">
            <div class="col-md-10 offset-md-1 content">
                <h3><?php echo xlt("Activities") ?><i class="ml-1" id="brand"></i></h3>
                <div id="dashboard" class="card">
                    <!-- Nav tabs -->
                    <ul id="tab-menu" class="nav nav-pills" role="tablist">
                        <li class="nav-item" role="presentation"><a class="nav-link active" href="#received" aria-controls="received" role="tab" data-toggle="tab"><?php echo xlt("Received") ?></a></li>
                        <li class="nav-item" role="presentation"><a class="nav-link" href="#sent" aria-controls="sent" role="tab" data-toggle="tab"><?php echo xlt("Sent") ?></a></li>
                        <li class="nav-item ringcentral" role="presentation"><a class="nav-link" href="#messages" aria-controls="messages" role="tab" data-toggle="tab"><?php echo xlt("SMS Log") ?></a></li>
                        <li class="nav-item ringcentral" role="presentation"><a class="nav-link" href="#logs" aria-controls="logs" role="tab" data-toggle="tab"><?php echo xlt("Call Log") ?></a></li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" href="#alertlogs" aria-controls="alertlogs" role="tab" data-toggle="tab"><?php echo xlt("Notifications Log") ?><span class="fa fa-redo ml-1" onclick="getNotificationLog(event,this)"
                                    title="<?php echo xla('Click to refresh using current date range. Refreshing just this tab.') ?>"></span></a>
                        </li>
                        <li class="nav-item" role="presentation"><a class="nav-link" href="#upLoad" aria-controls="logs" role="tab" data-toggle="tab"><?php echo xlt("Upload Fax") ?></a></li>
                    </ul>
                    <!-- Tab panes -->
                    <div class="tab-content">
                        <div role="tabpanel" class="container-fluid tab-pane fade" id="received">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped" id="rcvdetails">
                                    <thead>
                                    <tr>
                                        <th><?php echo xlt("Start Time") ?></th>
                                        <th class="twilio"><?php echo xlt("End Time") ?></th>
                                        <th class="ringcentral"><?php echo xlt("Type") ?></th>
                                        <th><?php echo xlt("Pages") ?></th>
                                        <th><?php echo xlt("From") ?></th>
                                        <th><?php echo xlt("To") ?></th>
                                        <th><?php echo xlt("Result") ?></th>
                                        <th class="ringcentral"><?php echo xlt("Download") ?></th>
                                        <th><?php echo xlt("View") ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td><?php echo xlt("No Items Try Refresh") ?></td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div role="tabpanel" class="container-fluid tab-pane fade" id="sent">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped" id="sentdetails">
                                    <thead>
                                    <tr>
                                        <th><?php echo xlt("Start Time") ?></th>
                                        <th class="twilio"><?php echo xlt("End Time") ?></th>
                                        <th class="ringcentral"><?php echo xlt("Type") ?></th>
                                        <th><?php echo xlt("Pages") ?></th>
                                        <th><?php echo xlt("From") ?></th>
                                        <th><?php echo xlt("To") ?></th>
                                        <th><?php echo xlt("Result") ?></th>
                                        <th class="ringcentral"><?php echo xlt("Download") ?></th>
                                        <th><?php echo xlt("View") ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td><?php echo xlt("No Items Try Refresh") ?></td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div role="tabpanel" class="container-fluid tab-pane fade" id="messages">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped" id="msgdetails">
                                    <thead>
                                    <tr>
                                        <th><?php echo xlt("Date") ?></th>
                                        <th><?php echo xlt("Type") ?></th>
                                        <th><?php echo xlt("From") ?></th>
                                        <th><?php echo xlt("To") ?></th>
                                        <th><?php echo xlt("Result") ?></th>
                                        <th class="twilio"><?php echo xlt("Download") ?></th>
                                        <th class="ringcentral"><?php echo xlt("Message") ?></th>
                                        <th><?php echo xlt("View") ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td><?php echo xlt("No Items Try Refresh") ?></td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div role="tabpanel" class="container-fluid tab-pane fade" id="logs">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped" id="logdetails">
                                    <thead>
                                    <tr>
                                        <th><?php echo xlt("Date") ?></th>
                                        <th><?php echo xlt("Type") ?></th>
                                        <th><?php echo xlt("From") ?></th>
                                        <th><?php echo xlt("To") ?></th>
                                        <th><?php echo xlt("Action") ?></th>
                                        <th><?php echo xlt("Result") ?></th>
                                        <th><?php echo xlt("Id") ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td><?php echo xlt("No Items Try Refresh") ?></td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div role="tabpanel" class="container-fluid tab-pane fade" id="alertlogs">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped" id="alertdetails">
                                    <thead>
                                    <tr>
                                        <th><?php echo xlt("Id") ?></th>
                                        <th><?php echo xlt("Date Sent") ?></th>
                                        <th><?php echo xlt("Appt Date Time") ?></th>
                                        <th><?php echo xlt("Patient") ?></th>
                                        <th><?php echo xlt("Message") ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td><?php echo xlt("No Items") ?></td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div role="tabpanel" class="container-fluid tab-pane fade in active" id="upLoad">
                            <div class="panel container-fluid">
                                <div id="fax-queue-container">
                                    <div id="fax-queue">
                                        <form id="faxQueue" method="post" enctype="multipart/form-data" class="dropzone"></form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div><!-- /.navbar-container -->
</body>
</html>

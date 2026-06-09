<?php
# *******************************************************************
# NEEDS
# *******************************************************************

require_once(__DIR__ . '/inc.startup.php');

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);


use Janmensik\Jmlib\Database;
use Janmensik\Jmlib\AppData;
use Janmensik\Jmlib\Modul;

require_once(__DIR__ . '/include/class.Dispatch.php');

# *******************************************************************
# DEFINICE, INICIALIZACE
# *******************************************************************

date_default_timezone_set('Europe/Prague');
mb_internal_encoding("UTF-8");

use PhpImap\Mailbox;
use PhpImap\Exceptions\ConnectionException;

# spusteni tridy Database
$DB = new Database(getenv('SQL_HOST'), getenv('SQL_DATABASE'), getenv('SQL_USER'), getenv('SQL_PASSWORD'));
$DB->query('SET CHARACTER SET utf8;');

$start_time_ts = microtime(true);
$start_time_db = date('Y-m-d H:i:s');

// Insert initial log entry
$DB->query("INSERT INTO import_log (cron_type, started_at, status) VALUES ('email_import', '$start_time_db', 'running')");
$log_id = $DB->getId();

// Create PhpImap\Mailbox instance for all further actions
$mailbox = new PhpImap\Mailbox(
    getenv('IMAP_HOSTNAME'), // IMAP server and mailbox folder
    getenv('IMAP_USERNAME'), // Username for the before configured mailbox
    getenv('IMAP_PASSWORD'), // Password for the before configured username
    false,
    'UTF-8'
);


if (!isset($Dispatch)) {
    $Dispatch = new \PozarniPoplach\Dispatch($DB);
}

# *******************************************************************
# PROGRAM
# *******************************************************************

$mailbox->setAttachmentsIgnore(true);

// Define folder names
$archive_folder = 'INBOX/Archive';
$dispatch_folder = 'INBOX/Dispatch';

try {
    // 1. Get UNSEEN emails first (highest priority)
    $unseen_ids = $mailbox->searchMailbox('UNSEEN');

    // 2. If we have room, get SEEN emails that are still in INBOX (to clean up)
    $seen_ids = [];
    if (count($unseen_ids) < 20) {
        $seen_ids = $mailbox->searchMailbox('SEEN');
        // Filter out any IDs already in unseen (shouldn't happen but safe)
        $seen_ids = array_diff($seen_ids, $unseen_ids);
    }

    // Combine: Unseen first, then Seen.
    // Both lists are typically returned oldest-to-newest by IMAP.
    $mail_ids = array_merge($unseen_ids, $seen_ids);

    // Limit to max 20 emails per run for stability
    $mail_ids = array_slice($mail_ids, 0, 20);

    // Ensure target folders exist
    $folders = $mailbox->getListingFolders();
    $targets = [$archive_folder, $dispatch_folder];

    foreach ($targets as $target) {
        $exists = false;
        foreach ($folders as $folder) {
            if (str_ends_with($folder, $target)) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $mailbox->createMailbox($target);
        }
    }
} catch (ConnectionException $ex) {
    $DB->query("UPDATE import_log SET status = 'error', finished_at = NOW() WHERE id = " . (int)$log_id);
    error_log("IMAP connection failed: " . $ex->getMessage());
    exit(1);
} catch (Exception $ex) {
    $DB->query("UPDATE import_log SET status = 'error', finished_at = NOW() WHERE id = " . (int)$log_id);
    error_log("An error occurred: " . $ex->getMessage());
    exit(1);
}


$emails_processed = $dispatches_created = 0;
$log_entries = array();

foreach ($mail_ids as $mail_id) {
    $iteration_timer = microtime(true);

    try {
        $email = $mailbox->getMail($mail_id, true); // Mark as seen immediately
        $unit_registration = $Dispatch->extractUnitRegistration($email->toString);

        // If not a dispatch email (no valid unit registration in To address), move to Archive and skip
        if (!$unit_registration) {
            $log_entries[] = "[INFO] Mail ID $mail_id is not a dispatch notification (skipping)";
            $mailbox->moveMail($mail_id, $archive_folder);
            $emails_processed++;
            continue;
        }

        $data = $Dispatch->linkParsedDispatch($Dispatch->parseDispatchHtml($email->textHtml), $unit_registration);
        $set = $Dispatch->prepareSave($data);

        $dispatch_id = $Dispatch->set($set, null, 'IODU');

        if ($dispatch_id) {
            $dispatches_created++;
            $log_entries[] = "[INFO] Dispatch $dispatch_id created/updated from Mail ID $mail_id";
        } else {
            // Even if not "created/updated" (e.g. duplicate), we should archive it
            // if we are certain it's a valid dispatch email that was processed.
            $log_entries[] = "[INFO] Mail ID $mail_id already processed (moving to $dispatch_folder)";
        }

        // Move valid dispatch emails to Dispatch folder only if no error occurred until now
        try {
            $mailbox->moveMail($mail_id, $dispatch_folder);
            $log_entries[] = "[INFO] Mail ID $mail_id moved to $dispatch_folder";
        } catch (Exception $e) {
            $log_entries[] = "[WARN] Failed to move Mail ID $mail_id to $dispatch_folder: " . $e->getMessage();
        }
    } catch (Exception $e) {
        $log_entries[] = "[ERROR] Mail ID $mail_id: " . $e->getMessage() . " (remains in INBOX)";
    } catch (Throwable $e) {
        $log_entries[] = "[FATAL] Mail ID $mail_id: " . $e->getMessage() . " (remains in INBOX)";
    }

    $emails_processed++;
}

$end_time_ts = microtime(true);
$duration = (int)round($end_time_ts - $start_time_ts);

// Update log entry
$DB->query("UPDATE import_log SET
    finished_at = NOW(),
    emails_processed = $emails_processed,
    dispatches_created = $dispatches_created,
    duration = $duration,
    status = 'success'
    WHERE id = " . (int)$log_id);

?>

<!DOCTYPE html>
<html lang="cs" class="h-full">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Cron Log • Požární poplach</title>
    <link rel="icon" href="./favicon.svg" type="image/svg+xml" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,100..900;1,100..900&display=swap');
        body { font-family: 'Public Sans', sans-serif; }
    </style>
</head>

<body class="bg-surface h-full flex items-center justify-center p-4">
    <div class="max-w-2xl w-full">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-primary flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <h1 class="text-white font-black text-xl uppercase tracking-tighter leading-none">Email Import</h1>
                    <p class="text-slate-500 text-[10px] font-bold uppercase tracking-widest mt-1">Cron Job Execution Log</p>
                </div>
            </div>
            <div class="bg-emerald-500/10 border border-emerald-500/20 px-3 py-1">
                <span class="text-emerald-500 text-[10px] font-black uppercase tracking-widest">Success</span>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-px bg-slate-800 border border-slate-800 mb-6">
            <div class="bg-[#0f1d24] p-4 text-center">
                <div class="text-slate-500 text-[9px] font-bold uppercase tracking-widest mb-1">Emails</div>
                <div class="text-white font-black text-2xl leading-none"><?php echo $emails_processed; ?></div>
            </div>
            <div class="bg-[#0f1d24] p-4 text-center">
                <div class="text-slate-500 text-[9px] font-bold uppercase tracking-widest mb-1">Dispatches</div>
                <div class="text-white font-black text-2xl leading-none"><?php echo $dispatches_created; ?></div>
            </div>
            <div class="bg-[#0f1d24] p-4 text-center">
                <div class="text-slate-500 text-[9px] font-bold uppercase tracking-widest mb-1">Duration</div>
                <div class="text-white font-black text-2xl leading-none"><?php echo $duration; ?><span class="text-sm font-bold ml-1 text-slate-500">s</span></div>
            </div>
            <div class="bg-[#0f1d24] p-4 text-center">
                <div class="text-slate-500 text-[9px] font-bold uppercase tracking-widest mb-1">Log ID</div>
                <div class="text-white font-black text-2xl leading-none">#<?php echo $log_id; ?></div>
            </div>
        </div>

        <!-- Log Output -->
        <div class="bg-[#0f1d24] border border-slate-800 p-6">
            <div class="flex items-center gap-2 mb-4 border-b border-slate-800 pb-4">
                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                <span class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Technical Log</span>
            </div>
            <div class="font-mono text-[11px] text-slate-400 space-y-1 overflow-auto max-h-75 scrollbar-hide">
                <div class="text-slate-600">[<?php echo $start_time_db; ?>] Initializing email import...</div>
                <div class="text-slate-600">[<?php echo $start_time_db; ?>] Connection to IMAP established.</div>
                <?php foreach ($log_entries as $entry) : ?>
                    <div class="<?php echo str_contains($entry, '[ERROR]') ? 'text-red-500' : 'text-slate-400'; ?>">
                        <?php echo htmlspecialchars($entry); ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($log_entries)) : ?>
                    <div class="text-slate-500 italic">No activity logs recorded.</div>
                <?php endif; ?>
                <div class="text-slate-600">[<?php echo date('H:i:s'); ?>] Execution finished in <?php echo $duration; ?>s.</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-6 flex justify-between items-center text-slate-600 text-[10px] font-bold uppercase tracking-widest">
            <div>© PožárníPoplach.cz</div>
            <div><?php echo date('d. m. Y H:i:s'); ?></div>
        </div>
    </div>
</body>

</html>

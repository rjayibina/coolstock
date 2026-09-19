<?php
/**
 * Views/users/index.php
 * Expects: $users (array of rows from the users table),
 *          $pagination (array: page, perPage, totalCount, totalPages)
 * Admin-only - access is gated in index.php before this controller runs.
 */
$status = $_GET['status'] ?? null;
$message = $_GET['message'] ?? '';
$currentSort = $_GET['sort'] ?? 'newest';
$pageTitle = 'Users';
$activeSection = 'users';
$count = $pagination['totalCount'];
$viewerId = current_user()['user_id'] ?? 0;
require __DIR__ . '/../partials/header.php';

/** Builds a pagination link that keeps the current sort */
function userPageUrl(int $page): string
{
    global $currentSort;
    return "index.php?module=users&action=index"
        . "&sort=" . urlencode($currentSort)
        . "&page=" . $page;
}
?>
        <div class="page-header">
            <div class="page-title-group">
                <h1 class="page-title">Users</h1>
                <span class="page-title-count"><?= $count ?> <?= $count === 1 ? 'user' : 'users' ?></span>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="userSearch" placeholder="Search users..." onkeyup="filterUsers()">
                </div>
                <button type="button" class="btn btn-primary" onclick="openAddUserModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add User
                </button>
            </div>
        </div>

        <?php if ($status === 'created'): ?>
            <div class="alert alert-success">User account created successfully.</div>
        <?php elseif ($status === 'updated'): ?>
            <div class="alert alert-success">User account updated successfully.</div>
        <?php elseif ($status === 'deactivated'): ?>
            <div class="alert alert-success">User account deactivated.</div>
        <?php elseif ($status === 'activated'): ?>
            <div class="alert alert-success">User account reactivated.</div>
        <?php elseif ($status === 'error'): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($message !== '' ? $message : 'Something went wrong. Please try again.') ?></div>
        <?php endif; ?>

        <div class="sort-bar">
            <label for="sortSelect">Sort by</label>
            <select id="sortSelect" onchange="location.href = 'index.php?module=users&action=index&sort=' + this.value">
                <option value="newest" <?= $currentSort === 'newest' ? 'selected' : '' ?>>Recently added</option>
                <option value="oldest" <?= $currentSort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                <option value="name_asc" <?= $currentSort === 'name_asc' ? 'selected' : '' ?>>Name: A–Z</option>
                <option value="name_desc" <?= $currentSort === 'name_desc' ? 'selected' : '' ?>>Name: Z–A</option>
            </select>
        </div>

        <div class="table-card">
            <table id="userTable">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th style="width:190px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr class="empty-row">
                            <td colspan="6">No users match these filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="cell-id"><?= (int) $u['user_id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                                    <?php if ((int) $u['user_id'] === (int) $viewerId): ?>
                                        <span class="cell-muted">(You)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-muted"><?= htmlspecialchars($u['email']) ?></td>
                                <td><span class="badge"><?= htmlspecialchars(User::roleLabel($u['role'])) ?></span></td>
                                <td>
                                    <?php if ((int) $u['is_active'] === 1): ?>
                                        <span class="badge" style="background:var(--success-bg);color:var(--success);">Active</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:var(--danger-bg);color:var(--danger);">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <button type="button" class="btn btn-edit btn-sm" onclick="openEditUserModal(<?= $u['user_id'] ?>)">Edit</button>
                                    <?php if ((int) $u['is_active'] === 1): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="openDeactivateUserModal(<?= $u['user_id'] ?>)">Deactivate</button>
                                    <?php else: ?>
                                        <a href="index.php?module=users&action=reactivate&id=<?= $u['user_id'] ?>" class="btn btn-success btn-sm">Reactivate</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination['totalCount'] > 0): ?>
            <?php
            $startRow = ($pagination['page'] - 1) * $pagination['perPage'] + 1;
            $endRow = min($pagination['page'] * $pagination['perPage'], $pagination['totalCount']);
            ?>
            <div class="pagination-bar">
                <span>Showing <?= $startRow ?>–<?= $endRow ?> of <?= $pagination['totalCount'] ?> users</span>
                <div class="pagination-controls">
                    <a href="<?= userPageUrl(max(1, $pagination['page'] - 1)) ?>" class="page-btn <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">&lsaquo; Prev</a>
                    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                        <a href="<?= userPageUrl($p) ?>" class="page-btn <?= $p === $pagination['page'] ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a href="<?= userPageUrl(min($pagination['totalPages'], $pagination['page'] + 1)) ?>" class="page-btn <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">Next &rsaquo;</a>
                </div>
            </div>
        <?php endif; ?>

        <div id="addUserModal" class="modal-overlay" onclick="if(event.target===this) closeModal('addUserModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Add User</h3>
                    <button type="button" class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php?module=users&action=create">
                        <label for="au_full_name">Full Name</label>
                        <input type="text" id="au_full_name" name="full_name" required>

                        <label for="au_email">Email</label>
                        <input type="email" id="au_email" name="email" required>

                        <label for="au_role">Role</label>
                        <select id="au_role" name="role" required>
                            <?php foreach (User::ROLES as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="au_password">Password</label>
                        <input type="password" id="au_password" name="password" minlength="8" required>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save User</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="editUserModal" class="modal-overlay" onclick="if(event.target===this) closeModal('editUserModal')">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Edit User</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editUserForm" action="index.php?module=users&action=edit">
                        <input type="hidden" name="user_id" id="eu_user_id" value="">

                        <label for="eu_full_name">Full Name</label>
                        <input type="text" id="eu_full_name" name="full_name" required>

                        <label for="eu_email">Email</label>
                        <input type="email" id="eu_email" name="email" required>

                        <label for="eu_role">Role</label>
                        <select id="eu_role" name="role" required>
                            <?php foreach (User::ROLES as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label>
                            <input type="checkbox" id="eu_is_active" name="is_active" value="1" style="width:auto;display:inline-block;margin-right:8px;">
                            Active
                        </label>

                        <label for="eu_password" style="margin-top:12px;">New Password <span class="cell-muted">(leave blank to keep current)</span></label>
                        <input type="password" id="eu_password" name="password" minlength="8" placeholder="••••••••">

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update User</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="deactivateUserModal" class="modal-overlay" onclick="if(event.target===this) closeModal('deactivateUserModal')">
            <div class="modal-dialog modal-dialog-sm">
                <div class="modal-header">
                    <h3>Deactivate User</h3>
                    <button type="button" class="modal-close" onclick="closeModal('deactivateUserModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Deactivate <strong id="du_name"></strong>? They won't be able to sign in until reactivated.</p>
                    <div class="form-actions">
                        <a id="du_confirm_link" href="#" class="btn btn-danger-solid">Deactivate</a>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('deactivateUserModal')">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        const usersData = <?= json_encode(array_column($users, null, 'user_id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        function closeModal(id) {
            document.getElementById(id)?.classList.remove('open');
        }

        function openAddUserModal() {
            document.getElementById('addUserModal').classList.add('open');
        }

        function openEditUserModal(id) {
            const u = usersData[id];
            if (!u) return;

            document.getElementById('eu_user_id').value = id;
            document.getElementById('editUserForm').action = 'index.php?module=users&action=edit&id=' + id;
            document.getElementById('eu_full_name').value = u.full_name || '';
            document.getElementById('eu_email').value = u.email || '';
            document.getElementById('eu_role').value = u.role || '';
            document.getElementById('eu_is_active').checked = Number(u.is_active) === 1;
            document.getElementById('eu_password').value = '';

            document.getElementById('editUserModal').classList.add('open');
        }

        function openDeactivateUserModal(id) {
            const u = usersData[id];
            if (!u) return;

            document.getElementById('du_name').textContent = u.full_name;
            document.getElementById('du_confirm_link').href = 'index.php?module=users&action=delete&id=' + id;
            document.getElementById('deactivateUserModal').classList.add('open');
        }

        function filterUsers() {
            const q = document.getElementById('userSearch').value.toLowerCase();
            document.querySelectorAll('#userTable tbody tr').forEach(row => {
                if (row.classList.contains('empty-row')) return;
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.getElementById('addUserModal')?.classList.remove('open');
                document.getElementById('editUserModal')?.classList.remove('open');
                document.getElementById('deactivateUserModal')?.classList.remove('open');
            }
        });
        </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>

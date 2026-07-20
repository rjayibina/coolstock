<?php
/**
 * Views/partials/category-combobox.php
 * Expects: $categories (array), $selectedCategoryId (int|string|null), $selectedCategoryName (string|null)
 * Renders a search-as-you-type category picker with inline "Add new category",
 * matching the reference combobox UI. Submits as a normal `category_id` field.
 */
$selectedCategoryId = $selectedCategoryId ?? '';
$selectedCategoryName = $selectedCategoryName ?? '';
?>
<label for="categoryTrigger">Category <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
<div class="combo" id="categoryCombo">
    <input type="hidden" name="category_id" id="category_id" value="<?= htmlspecialchars((string) $selectedCategoryId) ?>">

    <div class="combo-trigger" id="categoryTrigger" tabindex="0" onclick="toggleCategoryCombo()">
        <span id="categoryComboLabel"><?= $selectedCategoryName !== '' ? htmlspecialchars($selectedCategoryName) : 'Uncategorized' ?></span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
    </div>

    <div class="combo-panel" id="categoryComboPanel">
        <div class="combo-search">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="categoryComboSearch" placeholder="Search or type to add a category..." oninput="filterCategoryCombo()" autocomplete="off">
        </div>

        <div class="combo-add" id="categoryComboAdd" onclick="quickAddCategory()">
            <span id="categoryComboAddLabel">Add new category</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </div>

        <div class="combo-options" id="categoryComboOptions">
            <div class="combo-option" data-id="" data-name="Uncategorized" onclick="selectCategoryOption('', 'Uncategorized')">Uncategorized</div>
            <?php foreach ($categories as $cat): ?>
                <div class="combo-option" data-id="<?= $cat['category_id'] ?>" data-name="<?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>" onclick="selectCategoryOption('<?= $cat['category_id'] ?>', '<?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>')">
                    <?= htmlspecialchars($cat['category_name']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function toggleCategoryCombo() {
    const panel = document.getElementById('categoryComboPanel');
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) {
        document.getElementById('categoryComboSearch').value = '';
        filterCategoryCombo();
        document.getElementById('categoryComboSearch').focus();
    }
}

function selectCategoryOption(id, name) {
    document.getElementById('category_id').value = id;
    document.getElementById('categoryComboLabel').textContent = name;
    document.querySelectorAll('#categoryComboOptions .combo-option').forEach(opt => {
        opt.classList.toggle('selected', opt.dataset.id === id);
    });
    document.getElementById('categoryComboPanel').classList.remove('open');
}

function filterCategoryCombo() {
    const q = document.getElementById('categoryComboSearch').value.trim().toLowerCase();
    const options = document.querySelectorAll('#categoryComboOptions .combo-option');
    let exactMatch = false;

    options.forEach(opt => {
        const name = opt.dataset.name.toLowerCase();
        const matches = name.includes(q);
        opt.classList.toggle('hidden-by-search', q !== '' && !matches);
        if (q !== '' && name === q) exactMatch = true;
    });

    const addRow = document.getElementById('categoryComboAdd');
    if (q !== '' && !exactMatch) {
        document.getElementById('categoryComboAddLabel').textContent = 'Add new category: "' + document.getElementById('categoryComboSearch').value.trim() + '"';
        addRow.classList.add('show');
    } else {
        addRow.classList.remove('show');
    }
}

function quickAddCategory() {
    const name = document.getElementById('categoryComboSearch').value.trim();
    if (name === '') return;

    const addRow = document.getElementById('categoryComboAdd');
    addRow.style.opacity = '0.6';
    addRow.style.pointerEvents = 'none';

    const formData = new FormData();
    formData.append('category_name', name);

    fetch('index.php?module=categories&action=quickCreate', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            addRow.style.opacity = '';
            addRow.style.pointerEvents = '';
            if (data.error) {
                alert(data.error);
                return;
            }
            // Add it to the option list (skip if it already existed and is already there)
            const options = document.getElementById('categoryComboOptions');
            if (!options.querySelector('[data-id="' + data.id + '"]')) {
                const div = document.createElement('div');
                div.className = 'combo-option';
                div.dataset.id = data.id;
                div.dataset.name = data.name;
                div.textContent = data.name;
                div.onclick = () => selectCategoryOption(String(data.id), data.name);
                options.appendChild(div);
            }
            selectCategoryOption(String(data.id), data.name);
        })
        .catch(() => {
            addRow.style.opacity = '';
            addRow.style.pointerEvents = '';
            alert('Could not create the category. Please try again.');
        });
}

document.addEventListener('click', function (e) {
    const combo = document.getElementById('categoryCombo');
    if (combo && !e.target.closest('#categoryCombo')) {
        document.getElementById('categoryComboPanel').classList.remove('open');
    }
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.getElementById('categoryComboPanel')?.classList.remove('open');
    }
});
</script>

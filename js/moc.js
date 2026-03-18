// js/moc.js
let currentPage = 1;
let rowsPerPage = 10;
let allApplications =[];
let currentInnerTab = 'pending'; // 'pending' or 'processed'
let currentFilters = { crop: '', month: '' };

document.addEventListener('DOMContentLoaded', async () => {
    const session = await checkSession();
    if (!session || session.role !== 'moc') {
        window.location.href = 'index.html';
        return;
    }
    document.getElementById('userName').textContent = session.full_name;

    loadCropsForFilters();
    loadCropsForForms();

    // Main tab switching
    document.querySelectorAll('.main-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.main-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.main-tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            const tabId = btn.dataset.mainTab;
            document.getElementById(`main-${tabId}`).classList.add('active');
            if (tabId === 'applications') loadApplications();
        });
    });

    // Inner tab switching
    document.querySelectorAll('.inner-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.inner-tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentInnerTab = btn.dataset.innerTab;
            currentPage = 1;
            filterAndRenderApplications();
        });
    });

    // Filters
    document.getElementById('applyFilters').addEventListener('click', () => {
        currentFilters.crop = document.getElementById('filterCrop').value;
        currentFilters.month = document.getElementById('filterMonth').value;
        currentPage = 1;
        filterAndRenderApplications();
    });
    
    document.getElementById('resetFilters').addEventListener('click', () => {
        document.getElementById('filterCrop').value = '';
        document.getElementById('filterMonth').value = '';
        currentFilters = { crop: '', month: '' };
        currentPage = 1;
        filterAndRenderApplications();
    });

    // Pagination
    document.getElementById('prevPage').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            filterAndRenderApplications();
        }
    });
    
    document.getElementById('nextPage').addEventListener('click', () => {
        const totalPages = Math.ceil(getFilteredApplications().length / rowsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            filterAndRenderApplications();
        }
    });

    // Forms
    document.getElementById('registerImporterForm').addEventListener('submit', registerImporter);
    document.getElementById('snapshotForm').addEventListener('submit', addSnapshot);
    document.getElementById('forecastForm').addEventListener('submit', addForecast);
    document.getElementById('approveForm').addEventListener('submit', submitDecision);
    document.getElementById('logoutBtn').addEventListener('click', logout);

    loadApplications();
});

// ...[Keep loadCropsForFilters and loadCropsForForms exactly the same] ...
async function loadCropsForFilters() {
    const res = await fetch('api/moc.php?action=crops');
    const data = await res.json();
    if (data.status === 'success') {
        const select = document.getElementById('filterCrop');
        data.crops.forEach(c => {
            const option = document.createElement('option');
            option.value = c.crop_id;
            option.textContent = c.crop_name;
            select.appendChild(option);
        });
    }
}

async function loadCropsForForms() {
    const res = await fetch('api/moc.php?action=crops');
    const data = await res.json();
    if (data.status === 'success') {
        const snapshotSelect = document.getElementById('snapshotCrop');
        const forecastSelect = document.getElementById('forecastCrop');
        data.crops.forEach(c => {
            const option1 = document.createElement('option');
            option1.value = c.crop_id;
            option1.textContent = c.crop_name;
            snapshotSelect.appendChild(option1);
            const option2 = document.createElement('option');
            option2.value = c.crop_id;
            option2.textContent = c.crop_name;
            forecastSelect.appendChild(option2);
        });
    }
}

async function loadApplications() {
    try {
        const res = await fetch('api/moc.php?action=applications');
        const data = await res.json();
        if (data.status === 'success') {
            allApplications = data.applications;
            filterAndRenderApplications();
            updateStats();
        }
    } catch (err) {
        console.error("Failed to load applications:", err);
    }
}

function getFilteredApplications() {
    let filtered = allApplications.filter(app => {
        if (currentInnerTab === 'pending') return app.status === 'pending';
        else return app.status !== 'pending';
    });

    if (currentFilters.crop) {
        filtered = filtered.filter(app => app.crop_id == currentFilters.crop);
    }

    if (currentFilters.month) {
        filtered = filtered.filter(app => app.arrival_date.startsWith(currentFilters.month));
    }

    return filtered;
}

function filterAndRenderApplications() {
    const filtered = getFilteredApplications();
    renderTable(filtered);
    updatePagination(filtered.length);
}

function renderTable(apps) {
    const tbody = document.querySelector('#applicationsTable tbody');
    if (apps.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center">No applications found.</td></tr>';
        return;
    }

    const start = (currentPage - 1) * rowsPerPage;
    const paginatedApps = apps.slice(start, start + rowsPerPage);

    tbody.innerHTML = paginatedApps.map(app => {
        const conflictClass = app.conflict_level === 'high' ? 'badge-danger' : (app.conflict_level === 'moderate' ? 'badge-warning' : 'badge-success');
        const conflictText = app.conflict_level === 'none' ? 'None' : app.conflict_level;
        
        let statusClass = 'badge-warning'; // pending
        if (app.status === 'approved') statusClass = 'badge-success';
        if (app.status === 'capped') statusClass = 'badge-info';
        if (app.status === 'rejected') statusClass = 'badge-danger';

        let actions = '-';
        if (app.status === 'pending') {
            actions = `
                <button class="btn-outline btn-sm" onclick="approveApplication('${app.application_id}', ${app.requested_quantity_tons})">Process</button>
            `;
        }

        return `
            <tr>
                <td><small>${app.application_id}</small></td>
                <td>${escapeHtml(app.company_name)}</td>
                <td>${escapeHtml(app.crop_name)}</td>
                <td>${app.requested_quantity_tons}</td>
                <td>${app.arrival_date}</td>
                <td><span class="badge ${conflictClass}">${conflictText}</span></td>
                <td>${app.local_supply ?? '-'}</td>
                <td>${app.demand ?? '-'}</td>
                <td>${app.recommendation ?? '-'}</td>
                <td><span class="badge ${statusClass}">${app.status}</span></td>
                <td>${actions}</td>
            </tr>
        `;
    }).join('');
}

// FIX 1: Calculate "Approved this month" accurately
function updateStats() {
    const pending = allApplications.filter(a => a.status === 'pending').length;
    const highConflict = allApplications.filter(a => a.conflict_level === 'high').length;
    
    // Check arrivals for the current real-world month
    const currentMonth = new Date().toISOString().slice(0, 7); 
    const approvedThisMonth = allApplications.filter(a => 
        (a.status === 'approved' || a.status === 'capped') && 
        a.arrival_date.startsWith(currentMonth)
    ).length;
    
    document.getElementById('pendingCount').textContent = pending;
    document.getElementById('highConflictCount').textContent = highConflict;
    document.getElementById('approvedCount').textContent = approvedThisMonth;
}

// FIX 4: Prevent "Page 1 of 0" glitch
function updatePagination(totalItems) {
    const totalPages = Math.max(1, Math.ceil(totalItems / rowsPerPage));
    document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
    document.getElementById('prevPage').disabled = currentPage <= 1;
    document.getElementById('nextPage').disabled = currentPage >= totalPages;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// FIX 2: Store requested quantity in dataset for Capped logic
function approveApplication(id, requestedQty) {
    document.getElementById('approveAppId').value = id;
    document.getElementById('approveAppId').dataset.requestedQty = requestedQty; // Store original
    document.getElementById('approvedQty').value = requestedQty; // Prefill
    document.getElementById('approveModal').classList.remove('hidden');
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.add('hidden');
    document.getElementById('approveForm').reset();
}

// FIX 2: Evaluate Capped vs Approved vs Rejected dynamically
async function submitDecision(e) {
    e.preventDefault();
    const appId = document.getElementById('approveAppId').value;
    const requestedQty = parseFloat(document.getElementById('approveAppId').dataset.requestedQty);
    let approvedQty = parseFloat(document.getElementById('approvedQty').value);
    
    let status = 'approved';
    
    if (isNaN(approvedQty) || approvedQty <= 0) {
        status = 'rejected';
        approvedQty = 0;
    } else if (approvedQty < requestedQty) {
        status = 'capped';
    }

    await updateApplication(appId, status, approvedQty);
    closeApproveModal();
}

async function updateApplication(id, status, approvedQty = null) {
    const res = await fetch('api/moc.php?action=update_application', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ application_id: id, status, approved_quantity: approvedQty })
    });
    const data = await res.json();
    if (data.status === 'success') {
        loadApplications();
    } else {
        alert(data.message);
    }
}

// ... [Keep registerImporter, addSnapshot, addForecast exactly the same] ...
async function registerImporter(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    const res = await fetch('api/moc.php?action=register_importer', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    const result = await res.json();
    if (result.status === 'success') {
        alert('Importer registered successfully');
        e.target.reset();
    } else {
        alert(result.message);
    }
}

async function addSnapshot(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    if (data.region === '') data.region = null;
    const res = await fetch('api/moc.php?action=add_snapshot', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    const result = await res.json();
    if (result.status === 'success') {
        alert('Snapshot added');
        e.target.reset();
    } else {
        alert(result.message);
    }
}

async function addForecast(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    if (data.region === '') data.region = null;
    const res = await fetch('api/moc.php?action=add_forecast', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    const result = await res.json();
    if (result.status === 'success') {
        alert('Forecast added');
        e.target.reset();
    } else {
        alert(result.message);
    }
}

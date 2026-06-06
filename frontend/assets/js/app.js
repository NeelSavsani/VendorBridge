// VendorBridge - Core App JS

const API_BASE =
    window.location.origin +
    '/vendorbridge/php/index.php/api';

// ---- API CLIENT ----
const api = {
  async request(method, path, data = null) {
    const token = localStorage.getItem('vb_token');
    const opts = {
      method,
      headers: { 'Content-Type': 'application/json' },
    };
    if (token) opts.headers['Authorization'] = `Bearer ${token}`;
    if (data) opts.body = JSON.stringify(data);
    const res =
    await fetch(
        API_BASE + path,
        opts
    );

    let json;

    try {
        json =
            await res.json();
    }
    catch {
        json = {
            error:
                'Invalid server response'
        };
    }

    if (!res.ok) {

        throw {
            status: res.status,
            message:
                json.error ||
                'Request failed',
            data: json
        };
    }

    return json;
  },
  get: (path) => api.request('GET', path),
  post: (path, data) => api.request('POST', path, data),
  put: (path, data) => api.request('PUT', path, data),
  delete: (path) => api.request('DELETE', path),
};

// ---- AUTH ----
const auth = {
    user: null,
    token: null,

    init() {

        this.token =
            localStorage.getItem('vb_token');

        const userStr =
            localStorage.getItem('vb_user');

        if (userStr) {

            try {

                this.user =
                    JSON.parse(userStr);

            }
            catch (e) {

                console.error(
                    'Invalid vb_user in localStorage:',
                    userStr
                );

                localStorage.removeItem(
                    'vb_user'
                );

                localStorage.removeItem(
                    'vb_token'
                );

                this.user = null;
                this.token = null;
            }
        }
    },

    login(token, user) {

        this.token = token;
        this.user = user;

        localStorage.setItem(
            'vb_token',
            token
        );

        localStorage.setItem(
            'vb_user',
            JSON.stringify(user)
        );
    },

    logout() {

        this.token = null;
        this.user = null;

        localStorage.removeItem(
            'vb_token'
        );

        localStorage.removeItem(
            'vb_user'
        );

        window.location.href =
            '/vendorbridge/frontend/auth/login.html';
    },

    isLoggedIn() {

        return !!this.token &&
               !!this.user;
    },

    hasRole(...roles) {

        return this.user &&
               roles.includes(
                   this.user.role
               );
    },

    requireAuth() {

        if (!this.isLoggedIn()) {

            window.location.href =
                '/vendorbridge/frontend/auth/login.html';

            return false;
        }

        return true;
    }
};

// ---- TOAST ----
const toast = {
  container: null,
  init() {
    this.container = document.getElementById('toast-container');
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.id = 'toast-container';
      document.body.appendChild(this.container);
    }
  },
  show(message, type = 'info', duration = 3500) {
    if (!this.container) this.init();
    const icons = { success: '✓', error: '✕', info: 'ℹ' };
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span>${icons[type] || '•'}</span><span>${message}</span>`;
    t.onclick = () => t.remove();
    this.container.appendChild(t);
    setTimeout(() => t.remove(), duration);
  },
  success: (m) => toast.show(m, 'success'),
  error: (m) => toast.show(m, 'error'),
  info: (m) => toast.show(m, 'info'),
};

// ---- MODAL ----
const modal = {
  open(id) { document.getElementById(id)?.classList.remove('hidden'); },
  close(id) { document.getElementById(id)?.classList.add('hidden'); },
  closeAll() {
    document.querySelectorAll('.modal-overlay').forEach(m => m.classList.add('hidden'));
  }
};

// ---- UTILS ----
function fmt_currency(n) {
  return '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmt_date(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmt_datetime(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function time_ago(d) {
  const diff = Date.now() - new Date(d);
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  return `${Math.floor(hrs / 24)}d ago`;
}

function status_badge(status) {
  const map = {
    open: 'badge-accent', active: 'badge-green', closed: 'badge-gray',
    draft: 'badge-gray', submitted: 'badge-blue', under_review: 'badge-orange',
    selected: 'badge-green', rejected: 'badge-red', approved: 'badge-green',
    pending: 'badge-orange', confirmed: 'badge-green', delivered: 'badge-accent',
    cancelled: 'badge-red', sent: 'badge-blue', paid: 'badge-green',
    overdue: 'badge-red', inactive: 'badge-gray',
  };
  const cls = map[status] || 'badge-gray';
  return `<span class="badge ${cls}">${(status || '').replace(/_/g, ' ')}</span>`;
}

function role_label(role) {
  const map = {
    admin: '🔑 Admin',
    procurement_officer: '📋 Procurement Officer',
    vendor: '🏢 Vendor',
    manager: '✅ Manager',
  };
  return map[role] || role;
}

function activity_icon(action) {
  const icons = {
    LOGIN: '🔐', REGISTER: '👤',
    RFQ_CREATED: '📝', RFQ_UPDATED: '✏️',
    QUOTATION_SUBMITTED: '📤', QUOTATION_APPROVED: '✅',
    APPROVAL_REQUESTED: '⏳', APPROVAL_APPROVED: '✅', APPROVAL_REJECTED: '❌',
    PO_GENERATED: '📦', INVOICE_GENERATED: '🧾',
    VENDOR_CREATED: '🏢', VENDOR_UPDATED: '✏️',
  };
  for (const key of Object.keys(icons)) {
    if (action?.includes(key)) return icons[key];
  }
  return '•';
}

function avatar_initials(name) {
  if (!name) return 'U';
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

// ---- SIDEBAR RENDERER ----
function render_sidebar(activePage) {
  const user = auth.user;
  if (!user) return;

  const nav_items = [
    { page: 'dashboard', icon: '⊞', label: 'Dashboard', roles: ['all'] },
    { page: 'vendors', icon: '🏢', label: 'Vendors', roles: ['admin', 'procurement_officer', 'manager'] },
    { page: 'rfqs', icon: '📝', label: 'RFQs', roles: ['all'] },
    { page: 'quotations', icon: '💬', label: 'Quotations', roles: ['all'] },
    { page: 'approvals', icon: '✅', label: 'Approvals', roles: ['admin', 'manager', 'procurement_officer'] },
    { page: 'purchase-orders', icon: '📦', label: 'Purchase Orders', roles: ['admin', 'procurement_officer', 'vendor'] },
    { page: 'invoices', icon: '🧾', label: 'Invoices', roles: ['all'] },
    { page: 'activity', icon: '📋', label: 'Activity Logs', roles: ['admin', 'manager', 'procurement_officer'] },
    { page: 'reports', icon: '📊', label: 'Reports', roles: ['admin', 'manager', 'procurement_officer'] },
    { page: 'users', icon: '👥', label: 'Users', roles: ['admin', 'manager'] },
  ];

  const navHtml = nav_items
    .filter(i => i.roles.includes('all') || i.roles.includes(user.role))
    .map(i => `
      <div class="nav-item ${activePage === i.page ? 'active' : ''}" onclick="navigate('${i.page}')">
        <span class="nav-icon">${i.icon}</span>
        <span>${i.label}</span>
      </div>
    `).join('');

  const sidebarEl = document.querySelector('.sidebar');
  if (!sidebarEl) return;

  sidebarEl.innerHTML = `
    <div class="sidebar-logo" onclick="navigate('dashboard')">
      <div class="logo-icon">V</div>
      <div class="logo-text">Vendor<span>Bridge</span></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-title">Main Menu</div>
      ${navHtml}
    </nav>
    <div class="sidebar-footer">
      <div class="user-card" onclick="auth.logout()">
        <div class="user-avatar">${avatar_initials(user.name)}</div>
        <div class="user-info">
          <div class="user-name">${user.name}</div>
          <div class="user-role">${user.role?.replace('_', ' ')}</div>
        </div>
      </div>
    </div>
  `;
}

function navigate(page)
{
    const pages = {

        dashboard:
            '/vendorbridge/frontend/dashboard/dashboard.html',

        vendors:
            '/vendorbridge/frontend/vendors/vendors.html',

        rfqs:
            '/vendorbridge/frontend/rfq/rfqs.html',

        quotations:
            '/vendorbridge/frontend/quotations/quotations.html',

        approvals:
            '/vendorbridge/frontend/approvals/approvals.html',

        'purchase-orders':
            '/vendorbridge/frontend/purchase-orders/purchase-orders.html',

        invoices:
            '/vendorbridge/frontend/invoices/invoices.html',

        activity:
            '/vendorbridge/frontend/activity/activity.html',

        reports:
            '/vendorbridge/frontend/reports/reports.html',

        users:
            '/vendorbridge/frontend/dashboard/users.html'
    };

    if (pages[page]) {
        window.location.href =
            pages[page];
    }
}

// Init auth on load
auth.init();

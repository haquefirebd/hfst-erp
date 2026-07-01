<script lang="ts">
  import { onMount, tick } from 'svelte';
  import { supabase } from '$lib/supabaseClient';

  function isNetworkError(error: any): boolean {
    if (!error) return false;
    const msg = (error.message || '').toLowerCase();
    return msg.includes('fetch') || 
           msg.includes('network') || 
           msg.includes('connect') || 
           msg.includes('refused') || 
           msg.includes('timeout') || 
           msg.includes('cors') ||
           msg.includes('load failed') ||
           msg.includes('typeerror') ||
           !error.code;
  }

  // -------------------------------------------------------------
  // Supabase Auth & Token States
  // -------------------------------------------------------------
  let token = $state('');
  let isAuthenticated = $state(false);
  let isMenuOpen = $state(false);

  // Authentication Fields
  let authEmail = $state('');
  let authPassword = $state('');
  let authError = $state('');

  // Helper formatting function to clean up decimal outputs for non-ERP users
  function formatQty(val: any): string {
    const num = parseFloat(val);
    if (isNaN(num)) return '0';
    return num % 1 === 0 ? num.toFixed(0) : num.toFixed(2);
  }

  function formatMoney(val: any): string {
    const num = parseFloat(val);
    if (isNaN(num)) return '0';
    return Math.round(num).toLocaleString('en-US') + ' BDT';
  }

  function formatBDPhoneNumber(value: string): string {
    let digits = value.replace(/\D/g, '');
    
    if (digits.startsWith('880')) {
      digits = digits.substring(3);
    } else if (digits.startsWith('0')) {
      digits = digits.substring(1);
    }

    if (digits.length > 10) {
      digits = digits.substring(0, 10);
    }

    if (digits.length === 0) {
      return '';
    }
    if (digits.length <= 4) {
      return `+880 ${digits}`;
    }
    return `+880 ${digits.substring(0, 4)}-${digits.substring(4)}`;
  }

  function getRefillStatus(project: any) {
    if (!project.is_refilling_reminders || !project.starting_date) {
      return { status: 'n/a', text: 'No reminders', class: 'color-disabled' };
    }
    
    const start = new Date(project.starting_date);
    if (isNaN(start.getTime())) {
      return { status: 'n/a', text: 'Invalid date', class: 'color-disabled' };
    }
    
    const nextRefill = new Date(start);
    nextRefill.setFullYear(start.getFullYear() + 1);
    
    const now = new Date();
    const diffTime = nextRefill.getTime() - now.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    const dateStr = nextRefill.toISOString().split('T')[0];
    
    if (diffDays < 0) {
      return { status: 'overdue', text: `🔴 Overdue since ${dateStr} (${Math.abs(diffDays)} days ago)`, class: 'badge danger' };
    } else if (diffDays <= 30) {
      return { status: 'soon', text: `⚠️ Due soon on ${dateStr} (in ${diffDays} days)`, class: 'badge warning' };
    } else {
      return { status: 'ok', text: `🟢 Next Refill: ${dateStr} (in ${diffDays} days)`, class: 'badge success' };
    }
  }

  // -------------------------------------------------------------
  // Data Model States (Runes Mode)
  // -------------------------------------------------------------
  let projects = $state([
    { id: 'proj-1', name: 'High-Rise Tower Fire Sprinkler installation', client: 'DHAKA METROPOLIS DEVELOPERS LTD', location: 'Uttara, Dhaka' },
    { id: 'proj-2', name: 'Bashundhara City mall Alarm Retrofit', client: 'BASHUNDHARA GROUP', location: 'Baridhara, Dhaka' }
  ]);

  let items = $state([]);

  let warehouses = $state([
    { id: 'wh-1', name: 'Uttara Central Depot', location: 'Uttara, Dhaka', active: true },
    { id: 'wh-2', name: 'Chittagong Port Transit Warehouse', location: 'Halishahar, Chittagong', active: true }
  ]);

  // Stock Movement History Log
  let stockLedger = $state([]);

  // Serialized Assets Track
  let serializedAssets = $state([]);

  // Batches Track (Expiry & Hydrostatic Test compliance)
  let batches = $state([]);

  // Quote Revision Negotiation Tree
  let quotes = $state([]);

  // Challan Delivery Tracking
  let deliveryChallans = $state([]);

  // Invoices & Outstanding Payments
  let invoices = $state([]);

  // -------------------------------------------------------------
  // Dynamic UI States
  // -------------------------------------------------------------
  let activeTab = $state('dashboard');
  function switchTab(tab: string) {
    activeTab = tab;
    isMenuOpen = false;
  }
  let ledgerError = $state('');
  let ledgerSuccess = $state('');

  // Simple "Make Invoice" Form states
  let inputSimpleBilling = $state({
    customer_name: '',
    sku: 'FM200-CYL-120L',
    qty: 1,
    unit_price: 125000,
    is_vat_free: false
  });
  let simpleBillingError = $state('');
  let simpleBillingSuccess = $state('');
  let autoInvoiceNumber = $derived('INV-2026-0' + (invoices.length + 1));

  // Light/Dark Theme States
  let isDarkMode = $state(true);
  let logoSrc = $derived(isDarkMode ? '/logo-dark.svg' : '/logo-light.svg');

  // Compliance alerts visibility toggle
  let showComplianceReminder = $state(false);
  let showUserGuideModal = $state(false);
  let showInvoicePrintModal = $state(false);
  let selectedInvoiceForPrint = $state<any>(null);

  function openInvoicePrint(inv: any) {
    selectedInvoiceForPrint = inv;
    showInvoicePrintModal = true;
  }

  let googleClientId = $state('');
  let googleLoginHint = $state('redshieldsafety@gmail.com');
  let googleAccessToken = $state('');
  let isUploadingToDrive = $state(false);
  let driveSuccess = $state('');
  let driveError = $state('');
  let activeInvoiceForSilentExport = $state<any>(null);

  $effect(() => {
    if (typeof document !== 'undefined') {
      if (isDarkMode) {
        document.body.classList.remove('light-mode');
      } else {
        document.body.classList.add('light-mode');
      }
    }
  });

  // Product CRUD Form states
  let isEditingProduct = $state(false);
  let editProductId = $state('');
  let inputProduct = $state({ sku: '', name: '', type: 'Standard', average_consumption_rate: 1.0, lead_time_days: 30, unit: 'Pcs', initial_quantity: 0, selling_price: 0, purchase_price: 0 });
  let productError = $state('');
  let productSuccess = $state('');

  // Project CRUD Form states
  let inputProjectRecord = $state({ id: '', name: '', location: '', client_name: '', starting_date: '', is_refilling_project: false, is_refilling_reminders: false, contact_number: '', contact_person: '', is_supply_items: false, supplied_items_list: [{ sku: '', qty: 1 }] });
  let isEditingProject = $state(false);
  let projectError = $state('');
  let projectSuccess = $state('');

  // Quick-Add Item popup states
  let showItemCheckPopup = $state(false);
  let showQuickAddItemModal = $state(false);
  let quickAddItemSku = $state('');
  let quickAddItemName = $state('');
  let quickAddItemPrice = $state(0);
  let quickAddItemUnit = $state('Pcs');
  let quickAddItemType = $state('Standard');
  let tempUnregisteredIndex = $state(0);

  // Watch Inventory stock quick modifier states
  let editingStockSku = $state('');
  let tempStockValue = $state(0);

  // Interactive Form Inputs (Challan based)
  let inputLedger = $state({ sku: 'FM200-CYL-120L', from: 'Uttara Central Depot', to: 'High-Rise Tower Project', qty: 10, type: 'Stock Out', serial: '', batch: 'BAT-FM200-01' });
  let inputQuoteNegotiation = $state({ quote_id: 'Q-2026-001', grand_total: 4100000, notes: 'Client requested further round' });

  // Reactivity
  let currentStock = $derived(items.map(item => {
    let balance = item.initial_quantity || 0;
    stockLedger.forEach(l => {
      if (l.item_sku === item.sku) {
        if (l.to === 'Uttara Central Depot') balance += l.qty;
        if (l.from === 'Uttara Central Depot') balance -= l.qty;
      }
    });
    return { ...item, stock: balance };
  }));

  let complianceAlerts = $derived(batches.filter(b => {
    let dueDate = new Date(b.hydro_due);
    let today = new Date('2026-06-24');
    let diffDays = Math.ceil((dueDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
    return diffDays <= 30;
  }));

  let clientSearchQuery = $state('');
  let filteredClients = $derived(
    projects.filter(p => {
      const q = clientSearchQuery.toLowerCase().trim();
      if (!q) return true;
      return (
        (p.name || '').toLowerCase().includes(q) ||
        (p.client || '').toLowerCase().includes(q) ||
        (p.contact_person || '').toLowerCase().includes(q) ||
        (p.contact_number || '').toLowerCase().includes(q) ||
        (p.location || '').toLowerCase().includes(q)
      );
    })
  );

  // Calculate accounts receivable aging
  let arAging = $state({ current: 431250, days30: 0, days60: 0, days90: 0, days120: 0, total: 431250 });

  // -------------------------------------------------------------
  // Actions
  // -------------------------------------------------------------
  async function updateStockLevel(item: any, targetStock: number) {
    if (targetStock < 0) {
      alert('Stock level cannot be negative.');
      return;
    }

    const offset = item.stock - (item.initial_quantity || 0);
    const newInitialQty = targetStock - offset;

    const isSandbox = (token === 'hfst_erp_admin_sandbox_token' || !token);
    if (isSandbox) {
      const idx = items.findIndex(i => i.sku === item.sku);
      if (idx !== -1) {
        items[idx].initial_quantity = newInitialQty;
        items = [...items];
        persistLocalData();
        productSuccess = `Stock for ${item.sku} adjusted to ${targetStock} successfully (Sandbox).`;
        setTimeout(() => productSuccess = '', 4000);
      }
    } else {
      try {
        const { error } = await supabase
          .from('items')
          .update({ initial_quantity: newInitialQty })
          .eq('sku', item.sku);
        if (error) {
          alert('Error adjusting stock: ' + error.message);
        } else {
          await fetchItems();
          persistLocalData();
          productSuccess = `Stock for ${item.sku} adjusted to ${targetStock} successfully in Supabase!`;
          setTimeout(() => productSuccess = '', 4000);
        }
      } catch (err: any) {
        alert('Network error adjusting stock: ' + err.message);
      }
    }
  }

  async function fetchItems() {
    try {
      const { data, error } = await supabase.from('items').select('*').order('sku');
      if (data) {
        items = data.map((i: any) => ({
          id: i.id,
          sku: i.sku,
          name: i.name,
          type: i.type,
          unit: i.unit || 'Pcs',
          initial_quantity: parseFloat(i.initial_quantity || '0'),
          average_consumption_rate: parseFloat(i.average_consumption_rate || '0'),
          lead_time_days: parseInt(i.lead_time_days || '30'),
          reorder_point: parseFloat(i.average_consumption_rate || '0') * parseInt(i.lead_time_days || '30'),
          selling_price: parseFloat(i.selling_price || '0'),
          purchase_price: parseFloat(i.purchase_price || '0')
        }));
      }
    } catch (e) {
      console.warn('Database offline / Fetch failed', e);
    }
  }

  async function fetchProjects() {
    try {
      const { data } = await supabase.from('projects').select('*').order('name');
      if (data) {
        projects = data.map((p: any) => {
          const list = (p.supplied_items || '')
            .split(',')
            .filter(Boolean)
            .map((token: string) => {
              const parts = token.split(':');
              return {
                sku: parts[0] || '',
                qty: parseFloat(parts[1] || '1')
              };
            });
          return {
            id: p.id,
            name: p.name,
            client: p.client_name,
            location: p.location,
            starting_date: p.starting_date || '',
            is_refilling_project: !!p.is_refilling_project,
            is_refilling_reminders: !!p.is_refilling_reminders,
            contact_number: p.contact_number || '',
            contact_person: p.contact_person || '',
            supplied_items_list: list
          };
        });
      }
    } catch (e) {}
  }

  async function fetchWarehouses() {
    try {
      const { data } = await supabase.from('warehouses').select('*').order('name');
      if (data) {
        warehouses = data.map((w: any) => ({
          id: w.id,
          name: w.name,
          location: w.location,
          active: w.is_active
        }));
      }
    } catch (e) {}
  }

  async function fetchStockLedger() {
    try {
      const { data } = await supabase
        .from('stock_ledger')
        .select(`
          id, qty, type, date, serial,
          items (sku),
          source_wh: source_warehouse_id (name),
          dest_wh: dest_warehouse_id (name)
        `)
        .order('created_at', { ascending: true });
      if (data) {
        stockLedger = data.map((l: any) => ({
          id: l.id,
          item_sku: l.items?.sku || '',
          from: l.source_wh?.name || 'Supplier (Stock Entry)',
          to: l.dest_wh?.name || 'High-Rise Tower Project',
          qty: parseFloat(l.qty),
          type: l.type,
          serial: l.serial || undefined,
          date: l.date
        }));
      }
    } catch (e) {}
  }

  async function fetchSerializedAssets() {
    try {
      const { data } = await supabase
        .from('serialized_assets')
        .select(`
          serial_number, status, warranty_expiry_date,
          items (sku),
          projects (name)
        `);
      if (data) {
        serializedAssets = data.map((a: any) => ({
          serial: a.serial_number,
          sku: a.items?.sku || '',
          status: a.status,
          warranty: a.warranty_expiry_date,
          project: a.projects?.name || null
        }));
      }
    } catch (e) {}
  }

  async function fetchBatches() {
    try {
      const { data } = await supabase
        .from('batches')
        .select(`
          batch_number, qty, manufacturing_date, expiry_date, hydrostatic_test_due_date,
          items (sku)
        `);
      if (data) {
        batches = data.map((b: any) => ({
          batch: b.batch_number,
          sku: b.items?.sku || '',
          qty: parseFloat(b.qty),
          mfg: b.manufacturing_date,
          expiry: b.expiry_date,
          hydro_due: b.hydrostatic_test_due_date
        }));
      }
    } catch (e) {}
  }

  async function fetchQuotes() {
    try {
      const { data } = await supabase
        .from('quotes')
        .select(`
          id,
          projects (name),
          quote_versions (version_number, status, total, vat, date, notes)
        `);
      if (data) {
        quotes = data.map((q: any) => ({
          id: q.id,
          project: q.projects?.name || '',
          versions: (q.quote_versions || [])
            .map((v: any) => ({
              version: v.version_number,
              status: v.status,
              total: parseFloat(v.total),
              vat: parseFloat(v.vat),
              date: v.date,
              notes: v.notes
            }))
            .sort((a: any, b: any) => a.version - b.version)
        }));
      }
    } catch (e) {}
  }

  async function fetchDeliveryChallans() {
    try {
      const { data } = await supabase
        .from('delivery_challans')
        .select(`
          id, status, shipped_qty, invoiced_qty,
          projects (name),
          items (sku)
        `);
      if (data) {
        deliveryChallans = data.map((dc: any) => ({
          id: dc.id,
          project: dc.projects?.name || '',
          status: dc.status,
          item_sku: dc.items?.sku || '',
          shipped_qty: parseFloat(dc.shipped_qty),
          invoiced_qty: parseFloat(dc.invoiced_qty)
        }));
      }
    } catch (e) {}
  }

  async function fetchInvoices() {
    try {
      const { data } = await supabase
        .from('invoices')
        .select(`
          id, qty, price, vat_challan, status, date, due,
          projects (name),
          challan_id
        `);
      if (data) {
        invoices = data.map((inv: any) => ({
          id: inv.id,
          project: inv.projects?.name || 'Direct Sale',
          challan: inv.challan_id || 'Direct Billing',
          qty: parseFloat(inv.qty),
          price: parseFloat(inv.price),
          vat_challan: inv.vat_challan,
          status: inv.status,
          date: inv.date,
          due: inv.due
        }));
      }
      // Calculate outstanding aging
      let current = 0;
      let total = 0;
      invoices.forEach((inv: any) => {
        const subtotal = inv.qty * inv.price;
        const vat = inv.vat_challan?.includes('Free') ? 0 : subtotal * 0.15;
        const invTotal = subtotal + vat;
        if (inv.status !== 'Paid') {
          current += invTotal;
          total += invTotal;
        }
      });
      arAging = { current, days30: 0, days60: 0, days90: 0, days120: 0, total };
    } catch (e) {}
  }

  async function loadAllData() {
    if (token === 'hfst_erp_admin_sandbox_token' || !token) {
      console.log('Operating in offline Sandbox mode. Skipping database fetch.');
      return;
    }
    await fetchItems();
    await fetchProjects();
    await fetchWarehouses();
    await fetchStockLedger();
    await fetchSerializedAssets();
    await fetchBatches();
    await fetchQuotes();
    await fetchDeliveryChallans();
    await fetchInvoices();
  }

  async function handleLogin(e: Event) {
    e.preventDefault();
    authError = '';
    try {
      const { data, error } = await supabase.auth.signInWithPassword({
        email: authEmail,
        password: authPassword
      });
      if (error) {
        authError = error.message;
      } else if (data.session) {
        token = data.session.access_token;
        isAuthenticated = true;
        await loadAllData();
      }
    } catch (err) {
      authError = 'An error occurred during login.';
    }
  }

  async function handleLogout() {
    try {
      await supabase.auth.signOut();
    } catch (e) {}
    token = '';
    isAuthenticated = false;
    activeTab = 'dashboard';
  }

  async function handleSaveProduct(e: Event) {
    e.preventDefault();
    productError = '';
    productSuccess = '';

    const payload = {
      sku: inputProduct.sku.trim(),
      name: inputProduct.name.trim(),
      type: inputProduct.type,
      unit: inputProduct.unit,
      initial_quantity: inputProduct.initial_quantity,
      average_consumption_rate: inputProduct.average_consumption_rate,
      lead_time_days: inputProduct.lead_time_days,
      selling_price: inputProduct.selling_price,
      purchase_price: inputProduct.purchase_price
    };

    if (!payload.sku || !payload.name) {
      productError = 'Please fill out all fields.';
      return;
    }

    const isSandbox = (token === 'hfst_erp_admin_sandbox_token' || !token);
    if (!isSandbox) {
      try {
        let error;
        if (isEditingProduct) {
          const { error: err } = await supabase
            .from('items')
            .update({
              sku: payload.sku,
              name: payload.name,
              type: payload.type,
              unit: payload.unit,
              initial_quantity: payload.initial_quantity,
              average_consumption_rate: payload.average_consumption_rate,
              lead_time_days: payload.lead_time_days,
              selling_price: payload.selling_price,
              purchase_price: payload.purchase_price
            })
            .eq('id', editProductId);
          error = err;
        } else {
          const nextId = 'item-' + (items.length + 1);
          const { error: err } = await supabase
            .from('items')
            .insert({
              id: nextId,
              sku: payload.sku,
              name: payload.name,
              type: payload.type,
              unit: payload.unit,
              initial_quantity: payload.initial_quantity,
              average_consumption_rate: payload.average_consumption_rate,
              lead_time_days: payload.lead_time_days,
              selling_price: payload.selling_price,
              purchase_price: payload.purchase_price
            });
          error = err;
        }

        if (error) {
          if (isNetworkError(error)) {
            throw new Error('Network error');
          }
          productError = error.message;
          return;
        } else {
          productSuccess = isEditingProduct ? '✔ Product details updated.' : '✔ New product added successfully.';
          await fetchItems();
          resetProductForm();
          return;
        }
      } catch (err) {
        console.warn('Supabase offline, executing Sandbox fallback', err);
      }
    }

    // Sandbox Fallback
    if (isEditingProduct) {
      const index = items.findIndex(i => i.id === editProductId);
      if (index !== -1) {
        items[index] = {
          id: editProductId,
          sku: inputProduct.sku,
          name: inputProduct.name,
          type: inputProduct.type as any,
          unit: inputProduct.unit,
          initial_quantity: inputProduct.initial_quantity,
          average_consumption_rate: inputProduct.average_consumption_rate,
          lead_time_days: inputProduct.lead_time_days,
          reorder_point: inputProduct.average_consumption_rate * inputProduct.lead_time_days,
          selling_price: inputProduct.selling_price,
          purchase_price: inputProduct.purchase_price
        };
        items = [...items];
        persistLocalData();
        productSuccess = '✔ Product details updated (Sandbox).';
        resetProductForm();
      }
    } else {
      const skuExists = items.some(i => i.sku === inputProduct.sku);
      if (skuExists) {
        productError = 'This Item Code is already registered.';
        return;
      }
      items.push({
        id: 'item-' + (items.length + 1),
        sku: inputProduct.sku,
        name: inputProduct.name,
        type: inputProduct.type as any,
        unit: inputProduct.unit,
        initial_quantity: inputProduct.initial_quantity,
        average_consumption_rate: inputProduct.average_consumption_rate,
        lead_time_days: inputProduct.lead_time_days,
        reorder_point: inputProduct.average_consumption_rate * inputProduct.lead_time_days,
        selling_price: inputProduct.selling_price,
        purchase_price: inputProduct.purchase_price
      });
      items = [...items];
      persistLocalData();
      productSuccess = '✔ New product added successfully (Sandbox).';
      resetProductForm();
    }
  }

  async function handleDeleteProduct(id: string) {
    productError = '';
    productSuccess = '';

    const isSandbox = (token === 'hfst_erp_admin_sandbox_token' || !token);
    if (!isSandbox) {
      try {
        const { error } = await supabase
          .from('items')
          .delete()
          .eq('id', id);
        if (error) {
          if (isNetworkError(error)) {
            throw new Error('Network error');
          }
          if (error.code === '23503') { // Foreign Key Violation code
            productError = '❌ Deletion Denied: This item has historical delivery logs. Deleting it would damage stock audits.';
          } else {
            productError = error.message;
          }
          return;
        } else {
          productSuccess = '✔ Product deleted.';
          await fetchItems();
          return;
        }
      } catch (err) {
        console.warn('Supabase offline, executing Sandbox fallback', err);
      }
    }

    // Sandbox Fallback
    const targetItem = items.find(i => i.id === id);
    if (!targetItem) return;

    const hasLedger = stockLedger.some(l => l.item_sku === targetItem.sku);
    if (hasLedger) {
      productError = '❌ Deletion Denied: This item has historical delivery logs. Deleting it would damage stock audits.';
      return;
    }

    items = items.filter(i => i.id !== id);
    persistLocalData();
    productSuccess = '✔ Product deleted (Sandbox).';
  }

  function editProduct(item: any) {
    isEditingProduct = true;
    editProductId = item.id;
    inputProduct = {
      sku: item.sku,
      name: item.name,
      type: item.type,
      average_consumption_rate: item.average_consumption_rate,
      lead_time_days: item.lead_time_days,
      unit: item.unit || 'Pcs',
      initial_quantity: item.initial_quantity || 0,
      selling_price: item.selling_price || 0,
      purchase_price: item.purchase_price || 0
    };
  }

  function resetProductForm() {
    isEditingProduct = false;
    editProductId = '';
    inputProduct = { sku: '', name: '', type: 'Standard', average_consumption_rate: 1.0, lead_time_days: 30, unit: 'Pcs', initial_quantity: 0, selling_price: 0, purchase_price: 0 };
  }

  // -------------------------------------------------------------
  // Project Management CRUD Handlers
  // -------------------------------------------------------------
  async function handleSaveProject(e: Event) {
    e.preventDefault();
    projectError = '';
    projectSuccess = '';

    const serializedItems = inputProjectRecord.is_supply_items 
      ? inputProjectRecord.supplied_items_list
          .filter(item => item.sku.trim() !== '')
          .map(item => `${item.sku.trim()}:${item.qty || 1}`)
          .join(',')
      : null;

    const payload = {
      name: inputProjectRecord.name.trim(),
      client_name: inputProjectRecord.client_name.trim(),
      location: inputProjectRecord.location.trim(),
      starting_date: inputProjectRecord.starting_date || null,
      is_refilling_project: !!inputProjectRecord.is_refilling_project,
      is_refilling_reminders: !!inputProjectRecord.is_refilling_reminders,
      contact_number: inputProjectRecord.contact_number.trim() || null,
      contact_person: inputProjectRecord.contact_person.trim() || null,
      supplied_items: serializedItems
    };

    if (!payload.name || !payload.client_name || !payload.location) {
      projectError = 'Please fill in Name, Company, and Location.';
      return;
    }

    const isSandbox = (token === 'hfst_erp_admin_sandbox_token' || !token);

    if (isEditingProject) {
      const projId = inputProjectRecord.id;
      if (isSandbox) {
        const idx = projects.findIndex(p => p.id === projId);
        if (idx !== -1) {
          const list = (payload.supplied_items || '')
            .split(',')
            .filter(Boolean)
            .map((token: string) => {
              const parts = token.split(':');
              return {
                sku: parts[0] || '',
                qty: parseFloat(parts[1] || '1')
              };
            });
          projects[idx] = {
            id: projId,
            name: payload.name,
            client: payload.client_name,
            location: payload.location,
            starting_date: payload.starting_date || '',
            is_refilling_project: payload.is_refilling_project,
            is_refilling_reminders: payload.is_refilling_reminders,
            contact_number: payload.contact_number || '',
            contact_person: payload.contact_person || '',
            supplied_items_list: list
          };
          projects = [...projects];
          persistLocalData();
          projectSuccess = 'Project updated successfully (Sandbox mode).';
          resetProjectForm();
        }
      } else {
        try {
          const { error } = await supabase.from('projects').update(payload).eq('id', projId);
          if (error) {
            projectError = error.message;
          } else {
            projectSuccess = 'Project updated successfully in Supabase!';
            resetProjectForm();
            await fetchProjects();
            persistLocalData();
          }
        } catch (err) {
          projectError = 'Network error: could not update project.';
        }
      }
    } else {
      const nextProjId = 'proj-' + (projects.length > 0 ? Math.max(...projects.map(p => parseInt(p.id.split('-')[1] || '0'))) + 1 : 1);
      if (isSandbox) {
        const list = (payload.supplied_items || '')
          .split(',')
          .filter(Boolean)
          .map((token: string) => {
            const parts = token.split(':');
            return {
              sku: parts[0] || '',
              qty: parseFloat(parts[1] || '1')
            };
          });
        const newProj = {
          id: nextProjId,
          name: payload.name,
          client: payload.client_name,
          location: payload.location,
          starting_date: payload.starting_date || '',
          is_refilling_project: payload.is_refilling_project,
          is_refilling_reminders: payload.is_refilling_reminders,
          contact_number: payload.contact_number || '',
          contact_person: payload.contact_person || '',
          supplied_items_list: list
        };
        projects = [...projects, newProj];
        persistLocalData();
        projectSuccess = 'Project registered successfully (Sandbox mode).';
        resetProjectForm();
      } else {
        try {
          const { error } = await supabase.from('projects').insert({
            id: nextProjId,
            ...payload
          });
          if (error) {
            projectError = error.message;
          } else {
            projectSuccess = 'Project registered successfully in Supabase!';
            resetProjectForm();
            await fetchProjects();
            persistLocalData();
          }
        } catch (err) {
          projectError = 'Network error: could not register project.';
        }
      }
    }
  }

  async function handleDeleteProject(id: string) {
    projectError = '';
    projectSuccess = '';
    const isSandbox = (token === 'hfst_erp_admin_sandbox_token' || !token);
    
    if (isSandbox) {
      projects = projects.filter(p => p.id !== id);
      persistLocalData();
      projectSuccess = 'Project deleted successfully (Sandbox mode).';
    } else {
      try {
        const { error } = await supabase.from('projects').delete().eq('id', id);
        if (error) {
          if (error.code === '23503') {
            projectError = '❌ Deletion Denied: This project has active delivery challans or invoices associated with it.';
          } else {
            projectError = error.message;
          }
        } else {
          projectSuccess = 'Project deleted successfully from Supabase!';
          await fetchProjects();
          persistLocalData();
        }
      } catch (err) {
        projectError = 'Network error: could not delete project.';
      }
    }
  }

  function editProject(proj: any) {
    isEditingProject = true;
    const hasItems = proj.supplied_items_list && proj.supplied_items_list.length > 0;
    inputProjectRecord = {
      id: proj.id,
      name: proj.name,
      location: proj.location,
      client_name: proj.client,
      starting_date: proj.starting_date || '',
      is_refilling_project: !!proj.is_refilling_project,
      is_refilling_reminders: !!proj.is_refilling_reminders,
      contact_number: proj.contact_number || '',
      contact_person: proj.contact_person || '',
      is_supply_items: hasItems,
      supplied_items_list: hasItems 
        ? proj.supplied_items_list.map((item: any) => ({ sku: item.sku, qty: item.qty }))
        : [{ sku: '', qty: 1 }]
    };
  }

  function resetProjectForm() {
    isEditingProject = false;
    inputProjectRecord = { id: '', name: '', location: '', client_name: '', starting_date: '', is_refilling_project: false, is_refilling_reminders: false, contact_number: '', contact_person: '', is_supply_items: false, supplied_items_list: [{ sku: '', qty: 1 }] };
  }

  // -------------------------------------------------------------
  // Quick-Add Inventory Item Popup Handlers
  // -------------------------------------------------------------
  let tempUnregisteredSku = $state('');

  function handleCheckInventoryItem(index: number, e: Event) {
    const val = (e.target as HTMLInputElement).value.trim();
    if (!val) return;

    const exists = items.some(i => i.sku.toLowerCase() === val.toLowerCase() || i.name.toLowerCase() === val.toLowerCase());
    if (!exists) {
      tempUnregisteredSku = val;
      tempUnregisteredIndex = index;
      showItemCheckPopup = true;
    } else {
      const match = items.find(i => i.sku.toLowerCase() === val.toLowerCase() || i.name.toLowerCase() === val.toLowerCase());
      if (match) {
        inputProjectRecord.supplied_items_list[index].sku = match.sku;
      }
    }
  }

  function handleCancelItemPopup() {
    showItemCheckPopup = false;
    inputProjectRecord.supplied_items_list[tempUnregisteredIndex].sku = '';
    tempUnregisteredSku = '';
  }

  function handleOpenQuickAdd() {
    showItemCheckPopup = false;
    quickAddItemSku = tempUnregisteredSku;
    quickAddItemName = '';
    quickAddItemPrice = 0;
    quickAddItemUnit = 'Pcs';
    quickAddItemType = 'Standard';
    showQuickAddItemModal = true;
  }

  async function handleQuickSaveItem() {
    if (!quickAddItemSku.trim() || !quickAddItemName.trim()) {
      alert('SKU and Name are required.');
      return;
    }

    const newItemPayload = {
      sku: quickAddItemSku.trim(),
      name: quickAddItemName.trim(),
      type: quickAddItemType,
      average_consumption_rate: 1.0,
      lead_time_days: 30,
      unit: quickAddItemUnit,
      initial_quantity: 0,
      selling_price: quickAddItemPrice,
      purchase_price: 0
    };

    const isSandbox = (token === 'hfst_erp_admin_sandbox_token' || !token);
    if (isSandbox) {
      const sandboxItem = {
        id: 'item-' + (items.length > 0 ? Math.max(...items.map(i => parseInt(i.id.split('-')[1] || '0'))) + 1 : 1),
        ...newItemPayload,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };
      items = [...items, sandboxItem];
      persistLocalData();
      inputProjectRecord.supplied_items_list[tempUnregisteredIndex].sku = newItemPayload.sku;
      showQuickAddItemModal = false;
      alert(`Item ${newItemPayload.sku} added to catalog successfully (Sandbox).`);
    } else {
      const nextItemId = 'item-' + (items.length > 0 ? Math.max(...items.map(i => parseInt(i.id.split('-')[1] || '0'))) + 1 : 1);
      try {
        const { error } = await supabase.from('items').insert({
          id: nextItemId,
          ...newItemPayload
        });
        if (error) {
          alert('Error adding item: ' + error.message);
        } else {
          await loadAllData();
          inputProjectRecord.supplied_items_list[tempUnregisteredIndex].sku = newItemPayload.sku;
          showQuickAddItemModal = false;
          alert(`Item ${newItemPayload.sku} added to catalog successfully in Supabase!`);
        }
      } catch (err: any) {
        alert('Network error adding item: ' + err.message);
      }
    }
  }

  function addSupplyItemRow() {
    inputProjectRecord.supplied_items_list = [
      ...inputProjectRecord.supplied_items_list,
      { sku: '', qty: 1 }
    ];
  }

  function removeSupplyItemRow(index: number) {
    if (inputProjectRecord.supplied_items_list.length === 1) {
      inputProjectRecord.supplied_items_list = [{ sku: '', qty: 1 }];
    } else {
      inputProjectRecord.supplied_items_list = inputProjectRecord.supplied_items_list.filter((_, i) => i !== index);
    }
  }

  // -------------------------------------------------------------
  // Google Drive API Direct PDF Backup Handlers
  // -------------------------------------------------------------
  // -------------------------------------------------------------
  // Google Drive API Direct PDF Backup Handlers
  // -------------------------------------------------------------
  async function uploadInvoiceToDrive(invoice: any, accessToken: string, isSilent: boolean) {
    if (isSilent) {
      activeInvoiceForSilentExport = invoice;
      await tick();
    }

    try {
      const element = isSilent 
        ? document.getElementById('silent-invoice-export-container')
        : document.querySelector('.invoice-printable-area');

      if (!element) {
        isUploadingToDrive = false;
        driveError = 'Could not locate the invoice container template for PDF generation.';
        activeInvoiceForSilentExport = null;
        return;
      }

      const opt = {
        margin: 10,
        filename: `Invoice_${invoice.id}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
      };

      const pdfBlob = await (window as any).html2pdf().set(opt).from(element).outputPdf('blob');

      const metadata = {
        name: `Invoice_${invoice.id}.pdf`,
        mimeType: 'application/pdf'
      };

      const form = new FormData();
      form.append('metadata', new Blob([JSON.stringify(metadata)], { type: 'application/json' }));
      form.append('file', pdfBlob);

      const uploadRes = await fetch('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${accessToken}`
        },
        body: form
      });

      if (uploadRes.status === 200 || uploadRes.status === 204) {
        const resData = await uploadRes.json();
        driveSuccess = `🎉 Invoice ${invoice.id} successfully uploaded to Google Drive! (File ID: ${resData.id.substring(0, 10)}...)`;
      } else {
        const errText = await uploadRes.text();
        driveError = `Upload Error (${uploadRes.status}): ${errText}`;
        if (uploadRes.status === 401) {
          googleAccessToken = '';
          if (typeof sessionStorage !== 'undefined') {
            sessionStorage.removeItem('hfst_google_access_token');
          }
        }
      }
    } catch (uploadErr: any) {
      driveError = 'Failed converting or uploading PDF: ' + uploadErr.message;
    } finally {
      isUploadingToDrive = false;
      activeInvoiceForSilentExport = null;
    }
  }

  async function handleBackupToGoogleDrive(invoice: any) {
    if (!googleClientId.trim()) {
      alert('Please enter and save your Google Client ID first in the settings panel below.');
      return;
    }

    isUploadingToDrive = true;
    driveSuccess = '';
    driveError = '';

    if (googleAccessToken) {
      await uploadInvoiceToDrive(invoice, googleAccessToken, false);
      return;
    }

    if (typeof (window as any).google === 'undefined') {
      isUploadingToDrive = false;
      driveError = 'Google OAuth library is still loading. Please try again in 5 seconds.';
      return;
    }

    if (typeof (window as any).html2pdf === 'undefined') {
      isUploadingToDrive = false;
      driveError = 'PDF conversion library is still loading. Please try again in 5 seconds.';
      return;
    }

    try {
      const clientConfig: any = {
        client_id: googleClientId.trim(),
        scope: 'https://www.googleapis.com/auth/drive.file',
        callback: async (tokenResponse: any) => {
          if (tokenResponse.error) {
            isUploadingToDrive = false;
            driveError = 'Authentication failed: ' + tokenResponse.error;
            return;
          }

          googleAccessToken = tokenResponse.access_token;
          if (typeof sessionStorage !== 'undefined') {
            sessionStorage.setItem('hfst_google_access_token', googleAccessToken);
          }

          await uploadInvoiceToDrive(invoice, googleAccessToken, false);
        }
      };

      if (googleLoginHint.trim()) {
        clientConfig.hint = googleLoginHint.trim();
      }

      const client = (window as any).google.accounts.oauth2.initTokenClient(clientConfig);
      client.requestAccessToken();
    } catch (err: any) {
      isUploadingToDrive = false;
      driveError = 'OAuth Initialization Error: ' + err.message;
    }
  }

  function saveGoogleClientId() {
    if (typeof window !== 'undefined') {
      localStorage.setItem('hfst_google_client_id', googleClientId.trim());
      localStorage.setItem('hfst_google_login_hint', googleLoginHint.trim());
      alert('Google API Settings saved locally successfully!');
    }
  }

  async function handleDirectDriveUpload(invoice: any) {
    if (!googleClientId.trim()) {
      alert('Please enter and save your Google Client ID first in the settings panel.');
      return;
    }

    isUploadingToDrive = true;
    driveSuccess = '';
    driveError = '';

    if (googleAccessToken) {
      await uploadInvoiceToDrive(invoice, googleAccessToken, true);
      return;
    }

    if (typeof (window as any).google === 'undefined') {
      isUploadingToDrive = false;
      driveError = 'Google OAuth library is still loading. Please try again in a few seconds.';
      return;
    }

    if (typeof (window as any).html2pdf === 'undefined') {
      isUploadingToDrive = false;
      driveError = 'PDF conversion library is still loading. Please try again in a few seconds.';
      return;
    }

    try {
      const clientConfig: any = {
        client_id: googleClientId.trim(),
        scope: 'https://www.googleapis.com/auth/drive.file',
        callback: async (tokenResponse: any) => {
          if (tokenResponse.error) {
            isUploadingToDrive = false;
            driveError = 'Authentication failed: ' + tokenResponse.error;
            return;
          }

          googleAccessToken = tokenResponse.access_token;
          if (typeof sessionStorage !== 'undefined') {
            sessionStorage.setItem('hfst_google_access_token', googleAccessToken);
          }

          await uploadInvoiceToDrive(invoice, googleAccessToken, true);
        }
      };

      if (googleLoginHint.trim()) {
        clientConfig.hint = googleLoginHint.trim();
      }

      const client = (window as any).google.accounts.oauth2.initTokenClient(clientConfig);
      client.requestAccessToken();
    } catch (err: any) {
      isUploadingToDrive = false;
      driveError = 'OAuth Direct Init Error: ' + err.message;
    }
  }

  // -------------------------------------------------------------
  // Simple "Make Invoice" Flow handler (Direct Billing, No Fancy Steps)
  // -------------------------------------------------------------
  function handleSkuChange(e: Event) {
    const sku = (e.target as HTMLSelectElement).value;
    const selected = items.find(i => i.sku === sku);
    if (selected) {
      inputSimpleBilling.unit_price = selected.selling_price || 0;
    }
  }

  async function handleCreateSimpleInvoice(e: Event) {
    e.preventDefault();
    simpleBillingError = '';
    simpleBillingSuccess = '';

    if (!inputSimpleBilling.customer_name.trim()) {
      simpleBillingError = 'Please enter the Customer Name.';
      return;
    }
    if (inputSimpleBilling.qty <= 0) {
      simpleBillingError = 'Quantity must be at least 1.';
      return;
    }
    if (inputSimpleBilling.unit_price <= 0) {
      simpleBillingError = 'Unit Price must be greater than 0.';
      return;
    }

    const selectedItem = items.find(i => i.sku === inputSimpleBilling.sku);
    if (!selectedItem) {
      simpleBillingError = 'Invalid product selected.';
      return;
    }

    const nextInvId = autoInvoiceNumber;
    const subtotal = inputSimpleBilling.qty * inputSimpleBilling.unit_price;
    const vat = inputSimpleBilling.is_vat_free ? 0 : subtotal * 0.15;
    const finalTotal = subtotal + vat;
    const vatChallan = inputSimpleBilling.is_vat_free ? 'VAT Free (Exempt)' : 'NBR Mushak-6.3 #' + (9110 + invoices.length);

    const isSandbox = (token === 'hfst_erp_admin_sandbox_token' || !token);
    if (!isSandbox) {
      try {
        const { error } = await supabase
          .from('invoices')
          .insert({
            id: nextInvId,
            qty: inputSimpleBilling.qty,
            price: inputSimpleBilling.unit_price,
            vat_challan: vatChallan,
            status: 'Unpaid',
            date: '2026-06-24',
            due: '2026-07-24'
          });
        if (error) {
          if (isNetworkError(error)) {
            throw new Error('Network error');
          }
          simpleBillingError = error.message;
          return;
        } else {
          const createdInvoice = {
            id: nextInvId,
            project: 'Direct Sale: ' + inputSimpleBilling.customer_name.trim(),
            customer_name: inputSimpleBilling.customer_name.trim(),
            challan: 'Direct Billing',
            qty: inputSimpleBilling.qty,
            price: inputSimpleBilling.unit_price,
            vat_challan: vatChallan,
            status: 'Unpaid',
            date: '2026-06-24',
            due: '2026-07-24'
          };
          simpleBillingSuccess = `✔ Invoice ${nextInvId} for ${inputSimpleBilling.customer_name} issued successfully! Total Billed: ${formatMoney(finalTotal)}`;
          await fetchInvoices();
          inputSimpleBilling.customer_name = '';
          inputSimpleBilling.qty = 1;
          inputSimpleBilling.is_vat_free = false;
          handleDirectDriveUpload(createdInvoice);
          return;
        }
      } catch (err) {
        console.warn('Supabase offline, executing Sandbox fallback', err);
      }
    }

    // Sandbox Fallback
    const newInvoice = {
      id: nextInvId,
      project: 'Direct Sale: ' + inputSimpleBilling.customer_name.trim(),
      customer_name: inputSimpleBilling.customer_name.trim(),
      challan: 'Direct Billing',
      qty: inputSimpleBilling.qty,
      price: inputSimpleBilling.unit_price,
      vat_challan: vatChallan,
      status: 'Unpaid',
      date: '2026-06-24',
      due: '2026-07-24'
    };
    invoices = [...invoices, newInvoice];
    persistLocalData();
    arAging.current += finalTotal;
    arAging.total += finalTotal;
    simpleBillingSuccess = `✔ Invoice ${nextInvId} for ${inputSimpleBilling.customer_name} issued successfully! Total Billed: ${formatMoney(finalTotal)} (Sandbox)`;
    inputSimpleBilling.customer_name = '';
    inputSimpleBilling.qty = 1;
    inputSimpleBilling.is_vat_free = false;
    handleDirectDriveUpload(newInvoice);
  }

  // -------------------------------------------------------------
  // Stock Movement Logic Handlers
  // -------------------------------------------------------------
  async function handlePostLedger() {
    ledgerError = '';
    ledgerSuccess = '';

    const selectedItem = items.find(i => i.sku === inputLedger.sku);
    if (!selectedItem) {
      ledgerError = 'Please select a valid item.';
      return;
    }

    if (inputLedger.qty <= 0) {
      ledgerError = 'Alert: Movement quantity must be greater than 0.';
      return;
    }

    if (selectedItem.type === 'Serialized') {
      if (inputLedger.qty !== 1) {
        ledgerError = 'Alert: Serialized panels/equipment must be moved 1 unit at a time.';
        return;
      }
      if (!inputLedger.serial) {
        ledgerError = 'Alert: Unique Serial Number is required for this item.';
        return;
      }
      const asset = serializedAssets.find(a => a.serial === inputLedger.serial);
      if (inputLedger.type === 'Stock Out') {
        if (!asset || asset.status !== 'In-Stock') {
          ledgerError = `Alert: Serial number ${inputLedger.serial} is not present in warehouse.`;
          return;
        }
        
        try {
          await supabase
            .from('serialized_assets')
            .update({ status: 'Dispatched', project_id: 'proj-1' })
            .eq('serial_number', inputLedger.serial);
        } catch (e) {}

        asset.status = 'Dispatched';
        asset.project = 'High-Rise Tower';
      }
    }

    if (selectedItem.type === 'BatchManaged' && inputLedger.type === 'Stock Out') {
      const activeBatch = batches.find(b => b.batch === inputLedger.batch);
      if (!activeBatch) {
        ledgerError = 'Alert: Please specify a valid Batch Number.';
        return;
      }
      const dueDate = new Date(activeBatch.hydro_due);
      const today = new Date('2026-06-24');
      if (dueDate < today) {
        ledgerError = `Safety Lockout: Batch ${activeBatch.batch} failed hydrostatic safety interval. Dispatch BLOCKED.`;
        return;
      }
    }

    const nextLedgerId = 'LGR-' + (stockLedger.length + 101);
    const isSandbox = (token === 'hfst_erp_admin_sandbox_token' || !token);
    if (!isSandbox) {
      try {
        const { error } = await supabase
          .from('stock_ledger')
          .insert({
            id: nextLedgerId,
            item_id: selectedItem.id,
            source_warehouse_id: inputLedger.from === 'Uttara Central Depot' ? 'wh-1' : (inputLedger.from === 'Chittagong Port Transit Warehouse' ? 'wh-2' : null),
            dest_warehouse_id: inputLedger.to === 'Uttara Central Depot' ? 'wh-1' : (inputLedger.to === 'Chittagong Port Transit Warehouse' ? 'wh-2' : null),
            qty: inputLedger.qty,
            type: inputLedger.type,
            serial: inputLedger.serial || null,
            date: '2026-06-24'
          });
        if (error) {
          if (isNetworkError(error)) {
            throw new Error('Network error');
          }
          ledgerError = error.message;
          return;
        } else {
          ledgerSuccess = '✔ Physical stock transfer recorded. Warehouse balances updated.';
          await fetchStockLedger();
          if (selectedItem.type === 'Serialized') await fetchSerializedAssets();
          return;
        }
      } catch (err) {
        console.warn('Supabase offline, executing Sandbox fallback', err);
      }
    }

    // Sandbox Fallback
    stockLedger = [...stockLedger, {
      id: nextLedgerId,
      item_sku: inputLedger.sku,
      from: inputLedger.from,
      to: inputLedger.to,
      qty: inputLedger.qty,
      type: inputLedger.type,
      serial: inputLedger.serial || undefined,
      date: '2026-06-24'
    }];
    persistLocalData();
    ledgerSuccess = '✔ Physical stock transfer recorded (Sandbox).';
  }

  async function handleNegotiateQuote() {
    const parentQuote = quotes.find(q => q.id === inputQuoteNegotiation.quote_id);
    if (!parentQuote) return;

    const nextVer = parentQuote.versions.length + 1;
    const grand = inputQuoteNegotiation.grand_total;
    const vatAmount = grand * 0.15;

    const isSandbox = (token === 'hfst_erp_admin_sandbox_token' || !token);
    if (!isSandbox) {
      try {
        const { error } = await supabase
          .from('quote_versions')
          .insert({
            id: 'qv-' + inputQuoteNegotiation.quote_id + '-' + nextVer,
            quote_id: inputQuoteNegotiation.quote_id,
            version_number: nextVer,
            status: 'Draft',
            total: grand,
            vat: vatAmount,
            date: '2026-06-24',
            notes: inputQuoteNegotiation.notes
          });
        if (error) {
          if (isNetworkError(error)) {
            throw new Error('Network error');
          }
          console.error(error.message);
          return;
        } else {
          await supabase
            .from('quote_versions')
            .update({ status: 'Superseded' })
            .eq('quote_id', inputQuoteNegotiation.quote_id)
            .neq('version_number', nextVer)
            .neq('status', 'Approved');
          await fetchQuotes();
          return;
        }
      } catch (err) {
        console.warn('Supabase offline, executing Sandbox fallback', err);
      }
    }

    // Sandbox Fallback
    parentQuote.versions = parentQuote.versions.map(v => ({ ...v, status: v.status === 'Approved' ? 'Approved' : 'Superseded' }));
    parentQuote.versions.push({
      version: nextVer,
      status: 'Draft',
      total: grand,
      vat: vatAmount,
      date: '2026-06-24',
      notes: inputQuoteNegotiation.notes
    });
    quotes = [...quotes];
    persistLocalData();
  }

  let restockReminders = $state<Record<string, boolean>>({});

  function sendRestockReminder(sku: string, name: string) {
    restockReminders[sku] = true;
    productSuccess = `🔔 Restock reminder sent successfully for ${name} (${sku})!`;
    setTimeout(() => {
      productSuccess = '';
    }, 4000);
  }

  function initializeLocalStorage() {
    if (typeof window === 'undefined') return;
    
    const localGoogleClientId = localStorage.getItem('hfst_google_client_id');
    if (localGoogleClientId) {
      googleClientId = localGoogleClientId;
    }

    const localGoogleLoginHint = localStorage.getItem('hfst_google_login_hint');
    if (localGoogleLoginHint) {
      googleLoginHint = localGoogleLoginHint;
    }
    
    // Items
    const localItems = localStorage.getItem('hfst_items');
    if (localItems) {
      items = JSON.parse(localItems);
    } else {
      localStorage.setItem('hfst_items', JSON.stringify(items));
    }

    // Stock Ledger
    const localLedger = localStorage.getItem('hfst_stock_ledger');
    if (localLedger) {
      stockLedger = JSON.parse(localLedger);
    } else {
      localStorage.setItem('hfst_stock_ledger', JSON.stringify(stockLedger));
    }

    // Invoices
    const localInvoices = localStorage.getItem('hfst_invoices');
    if (localInvoices) {
      invoices = JSON.parse(localInvoices);
      let current = 0;
      let total = 0;
      invoices.forEach((inv: any) => {
        const subtotal = inv.qty * inv.price;
        const vat = inv.vat_challan?.includes('Free') ? 0 : subtotal * 0.15;
        const invTotal = subtotal + vat;
        if (inv.status !== 'Paid') {
          current += invTotal;
          total += invTotal;
        }
      });
      arAging = { current, days30: 0, days60: 0, days90: 0, days120: 0, total };
    } else {
      localStorage.setItem('hfst_invoices', JSON.stringify(invoices));
    }

    // Serialized Assets
    const localAssets = localStorage.getItem('hfst_serialized_assets');
    if (localAssets) {
      serializedAssets = JSON.parse(localAssets);
    } else {
      localStorage.setItem('hfst_serialized_assets', JSON.stringify(serializedAssets));
    }

    // Batches
    const localBatches = localStorage.getItem('hfst_batches');
    if (localBatches) {
      batches = JSON.parse(localBatches);
    } else {
      localStorage.setItem('hfst_batches', JSON.stringify(batches));
    }

    // Quotes
    const localQuotes = localStorage.getItem('hfst_quotes');
    if (localQuotes) {
      quotes = JSON.parse(localQuotes);
    } else {
      localStorage.setItem('hfst_quotes', JSON.stringify(quotes));
    }

    // Projects
    const localProjects = localStorage.getItem('hfst_projects');
    if (localProjects) {
      projects = JSON.parse(localProjects);
    } else {
      localStorage.setItem('hfst_projects', JSON.stringify(projects));
    }
  }

  function persistLocalData() {
    if (typeof window === 'undefined') return;
    localStorage.setItem('hfst_items', JSON.stringify(items));
    localStorage.setItem('hfst_stock_ledger', JSON.stringify(stockLedger));
    localStorage.setItem('hfst_invoices', JSON.stringify(invoices));
    localStorage.setItem('hfst_serialized_assets', JSON.stringify(serializedAssets));
    localStorage.setItem('hfst_batches', JSON.stringify(batches));
    localStorage.setItem('hfst_quotes', JSON.stringify(quotes));
    localStorage.setItem('hfst_projects', JSON.stringify(projects));
  }

  onMount(async () => {
    if (typeof document !== 'undefined' && !document.getElementById('google-gsi-client')) {
      const script = document.createElement('script');
      script.id = 'google-gsi-client';
      script.src = 'https://accounts.google.com/gsi/client';
      script.async = true;
      script.defer = true;
      document.head.appendChild(script);
    }
    if (typeof document !== 'undefined' && !document.getElementById('html2pdf-cdn')) {
      const script = document.createElement('script');
      script.id = 'html2pdf-cdn';
      script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
      script.async = true;
      document.head.appendChild(script);
    }
    if (typeof sessionStorage !== 'undefined') {
      const cachedToken = sessionStorage.getItem('hfst_google_access_token');
      if (cachedToken) {
        googleAccessToken = cachedToken;
      }
    }
    initializeLocalStorage();
    try {
      const { data: { session } } = await supabase.auth.getSession();
      if (session) {
        token = session.access_token;
        isAuthenticated = true;
        await loadAllData();
      }
      
      const { data: { subscription } } = supabase.auth.onAuthStateChange(async (event, session) => {
        if (session) {
          token = session.access_token;
          isAuthenticated = true;
          await loadAllData();
        } else {
          token = '';
          isAuthenticated = false;
        }
      });

      return () => {
        subscription.unsubscribe();
      };
    } catch (e) {
      console.warn('Supabase Auth listener failed initialization. Operating in Sandbox Fallback Mode.', e);
    }
  });
</script>

<svelte:head>
  <title>HFST ERP - Fire Safety Enterprise Resource Planner</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono&display=swap" rel="stylesheet" />
</svelte:head>

{#if !isAuthenticated}
  <div class="login-wrapper">
    <div class="login-card">
      <div class="login-header">
        <a href="/" class="logo-link" title="Refresh portal">
          <div class="flame-logo">
            <img src={logoSrc} alt="HFST Logo" class="app-logo-img" />
          </div>
        </a>
        <h2>HFST FIRE SECURITY</h2>
        <p>Staff Login Portal</p>
      </div>

      {#if authError}
        <div class="alert error">{authError}</div>
      {/if}

      <form onsubmit={handleLogin} class="standard-form">
        <label>
          Login Email
          <input type="email" bind:value={authEmail} placeholder="yourname@example.com" required />
        </label>
        <label>
          Login Password
          <input type="password" bind:value={authPassword} placeholder="••••••••" required />
        </label>
        <button type="submit" class="btn btn-primary">Login Now</button>
      </form>

      <div class="login-footer">
        <button type="button" onclick={() => isDarkMode = !isDarkMode} class="btn-theme-toggle-login" aria-label="Toggle Theme" title="Toggle Light/Dark Mode">
          {#if isDarkMode}☀️ Light Mode{:else}🌙 Dark Mode{/if}
        </button>
      </div>
    </div>

    <!-- Popup 1: Item Not Found Warning Dialog -->
    {#if showItemCheckPopup}
      <div class="modal-backdrop" onclick={handleCancelItemPopup}>
        <div class="modal-card" onclick={(e) => e.stopPropagation()} style="max-width: 450px !important; text-align: center; padding: 24px !important;">
          <div style="font-size: 40px; margin-bottom: 12px;">🔍</div>
          <h2 style="margin: 0 0 12px 0; color: #f97316;">Item Not In Inventory</h2>
          <p style="font-size: 14px; color: #94a3b8; line-height: 1.5; margin-bottom: 24px;">
            The item SKU/Code <strong>"{tempUnregisteredSku}"</strong> was not found in the current warehouse inventory catalog. Would you like to add it now?
          </p>
          <div style="display: flex; gap: 12px; justify-content: center;">
            <button type="button" onclick={handleOpenQuickAdd} class="btn btn-primary" style="min-width: 120px;">Add Now</button>
            <button type="button" onclick={handleCancelItemPopup} class="btn btn-cancel" style="min-width: 120px;">Later</button>
          </div>
        </div>
      </div>
    {/if}

    <!-- Popup 2: Quick-Add Item Modal Form -->
    {#if showQuickAddItemModal}
      <div class="modal-backdrop" onclick={() => showQuickAddItemModal = false}>
        <div class="modal-card" onclick={(e) => e.stopPropagation()} style="max-width: 480px !important; padding: 24px !important;">
          <div class="modal-header">
            <h2 style="margin: 0; color: #cbd5e1;">🛠 Quick Add Inventory Item</h2>
            <button type="button" onclick={() => showQuickAddItemModal = false} class="btn-close-modal" aria-label="Close Modal">✕</button>
          </div>
          <form onsubmit={(e) => { e.preventDefault(); handleQuickSaveItem(); }} class="standard-form" style="margin-top: 15px;">
            <label>
              Item Code / SKU *
              <input type="text" bind:value={quickAddItemSku} required placeholder="e.g. FD-SMK-900" />
            </label>
            <label>
              Equipment / Item Name *
              <input type="text" bind:value={quickAddItemName} required placeholder="e.g. Optical Smoke Detector" />
            </label>
            <div class="form-row-2">
              <label>
                Tracking Method
                <select bind:value={quickAddItemType}>
                  <option value="Standard">Standard (Bulk counts)</option>
                  <option value="Serialized">Serialized Asset</option>
                  <option value="BatchManaged">Batch Managed</option>
                  <option value="KitAssembly">Kit Assembly</option>
                </select>
              </label>
              <label>
                Unit of Measure
                <select bind:value={quickAddItemUnit}>
                  <option value="Pcs">Pcs (Pieces)</option>
                  <option value="Mtrs">Mtrs (Meters)</option>
                  <option value="Sets">Sets</option>
                  <option value="Kits">Kits</option>
                  <option value="Kgs">Kgs</option>
                </select>
              </label>
            </div>
            <label>
              Standard Selling Price (BDT) *
              <input type="number" step="0.01" bind:value={quickAddItemPrice} min="0" required />
            </label>
            <div class="form-actions" style="margin-top: 15px;">
              <button type="submit" class="btn btn-primary" style="width: 100%;">Save to Inventory & Select</button>
              <button type="button" onclick={() => showQuickAddItemModal = false} class="btn btn-cancel" style="width: 100%;">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    {/if}
  </div>
{:else}
  <main class="erp-container">
    <!-- Mobile Sidebar Backdrop Overlay -->
    {#if isMenuOpen}
      <div class="mobile-sidebar-backdrop" onclick={() => isMenuOpen = false}></div>
    {/if}

    <!-- Side Navigation Bar -->
    <aside class="sidebar" class:open={isMenuOpen}>
      <button onclick={() => switchTab('dashboard')} class="logo-btn" title="Go to Dashboard">
        <div class="logo">
          <div class="flame-icon">
            <img src={logoSrc} alt="HFST Logo" class="sidebar-logo-img" />
          </div>
          <div class="logo-text">
            <h2>HFST ERP</h2>
            <span>Fire Safety Software</span>
          </div>
        </div>
      </button>
      <nav class="nav-links">
        <button class:active={activeTab === 'dashboard'} onclick={() => switchTab('dashboard')}>📊 Summary Dashboard</button>
        <button class:active={activeTab === 'manage-projects'} onclick={() => switchTab('manage-projects')}>🏢 Manage Projects</button>
        <button class:active={activeTab === 'client-contacts'} onclick={() => switchTab('client-contacts')}>👥 Client Directory</button>
        <button class:active={activeTab === 'make-invoice'} onclick={() => switchTab('make-invoice')}>✍ Make Invoice</button>
        <button class:active={activeTab === 'products'} onclick={() => switchTab('products')}>🛠 Add an Item</button>
        <button class:active={activeTab === 'sales'} onclick={() => switchTab('sales')}>💼 Prices & Quotations</button>
        <button class:active={activeTab === 'inventory'} onclick={() => switchTab('inventory')}>📦 Deliveries & Stock</button>
        <button class:active={activeTab === 'billing'} onclick={() => switchTab('billing')}>🧾 Customer Invoices</button>
        <button class:active={activeTab === 'watch-inventory'} onclick={() => switchTab('watch-inventory')}>👀 Watch Inventory</button>
      </nav>

      <div class="sidebar-footer">
        <button onclick={handleLogout} class="btn-logout">Logout</button>
      </div>
    </aside>

    <!-- Main Display Screen -->
    <section class="main-content">
      <header class="content-header">
        <div class="header-title-row">
          <button type="button" class="btn-mobile-menu-toggle" onclick={() => isMenuOpen = !isMenuOpen} aria-label="Toggle Menu" title="Toggle Menu">
            ☰
          </button>
          <h1>HFST Fire Security & Protection ERP</h1>
        </div>
        <div class="header-status-area">
          <button type="button" onclick={() => showUserGuideModal = true} class="btn-theme-toggle-header btn-user-guide" aria-label="User Guide" title="Open User Guide">
            📖 User Guide
          </button>
          <button type="button" onclick={() => isDarkMode = !isDarkMode} class="btn-theme-toggle-header" aria-label="Toggle Theme" title="Toggle Light/Dark Mode">
            {#if isDarkMode}☀️ Light Mode{:else}🌙 Dark Mode{/if}
          </button>
          <div class="header-status">
            <span class="pulse-green"></span> System Connected
          </div>
        </div>
      </header>

      <!-- Tab 1: Dashboard Summary -->
      {#if activeTab === 'dashboard'}
        <div class="tab-pane">
          <div class="guide-box">
            <h4>💡 Welcome to your HFST Dashboard!</h4>
            <p>This software helps you track sales quotes, warehouse levels, and customer bills. Below are your quick stats. If any alert flashes red, it means you need to restock or run safety checks.</p>
          </div>

          <!-- Metric Cards -->
          <div class="metrics-grid">
            <div class="metric-card bg-solid-gray">
              <h3>Active Project Sites</h3>
              <p class="value">{projects.length}</p>
              <span>Current customer install jobs</span>
            </div>
            <div class="metric-card bg-solid-gray">
              <h3>Total Stock Items</h3>
              <p class="value">{formatQty(currentStock.reduce((acc, curr) => acc + curr.stock, 0))} Units</p>
              <span>Current stock in Uttara Depot</span>
            </div>
            <div class="metric-card bg-solid-gray border-orange">
              <h3>Total Money Owed by Customers</h3>
              <p class="value">{formatMoney(arAging.total)}</p>
              <span>Outstanding invoice balance</span>
            </div>
            <div class="metric-card bg-solid-red">
              <h3>Safety Tests Due</h3>
              <p class="value">{complianceAlerts.length}</p>
              <span>Extinguishers/Cylinders needing check</span>
            </div>
          </div>

          <div class="dashboard-body">
            <!-- Inventory Balance Check -->
            <div class="card card-wide">
              <h2>Current Warehouse Inventory (Stock levels)</h2>
              <div class="responsive-table-container">
                <table>
                  <thead>
                    <tr>
                      <th>Item Code</th>
                      <th>Product Name</th>
                      <th>Tracking Method</th>
                      <th>Stock Balance</th>
                      <th>Alert Level</th>
                      <th>Safety Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {#each currentStock as item}
                      <tr>
                        <td><code class="code-sku">{item.sku}</code></td>
                        <td>{item.name}</td>
                        <td><span class="badge category">{item.type}</span></td>
                        <td class="text-bold">{formatQty(item.stock)} {item.unit || 'Pcs'}</td>
                        <td>{formatQty(item.reorder_point || (item.average_consumption_rate * item.lead_time_days))} {item.unit || 'Pcs'}</td>
                        <td>
                          {#if item.stock <= (item.reorder_point || (item.average_consumption_rate * item.lead_time_days))}
                            <span class="badge danger">Needs Restock</span>
                          {:else}
                            <span class="badge success">Stock Safe</span>
                          {/if}
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Accounts Receivable Aging Chart -->
            <div class="card card-narrow">
              <h2>Payment Deadlines</h2>
              <div class="aging-buckets">
                <div class="bucket-bar">
                  <span>Current (Due this month)</span>
                  <div class="bar-fill blue" style="width: 100%"></div>
                  <span class="amount">{formatMoney(arAging.current)}</span>
                </div>
                <div class="bucket-bar">
                  <span>Overdue (30 Days Past)</span>
                  <div class="bar-fill" style="width: 0%"></div>
                  <span class="amount">0 BDT</span>
                </div>
                <div class="bucket-bar">
                  <span>Overdue (60 Days Past)</span>
                  <div class="bar-fill" style="width: 0%"></div>
                  <span class="amount">0 BDT</span>
                </div>
                <div class="bucket-bar">
                  <span>Overdue (90+ Days Past)</span>
                  <div class="bar-fill" style="width: 0%"></div>
                  <span class="amount">0 BDT</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Compliance Warnings Ribbon -->
          {#if complianceAlerts.length > 0}
            <div class="compliance-reminder-container">
              <button onclick={() => showComplianceReminder = !showComplianceReminder} class="btn-reminder-toggle" class:active={showComplianceReminder}>
                🔔 Soft Reminder ({complianceAlerts.length})
              </button>

              {#if showComplianceReminder}
                <div class="card warning-panel">
                  <h3>⚠️ ATTENTION: Hydrostatic Testing Deadline Approaching (NFPA 10)</h3>
                  <p>Under safety guidelines, the following cylinder lots are due for pressure/refill testing soon. Do not dispatch these cylinders until they have been inspected.</p>
                  <ul>
                    {#each complianceAlerts as alert}
                      <li>
                        Lot <strong>{alert.batch}</strong> ({alert.sku}) must be tested by <strong>{alert.hydro_due}</strong>.
                      </li>
                    {/each}
                  </ul>
                </div>
              {/if}
            </div>
          {/if}

          <!-- Projects Refilling compliance alerts -->
          {#if projects.some(p => p.is_refilling_project && getRefillStatus(p).status !== 'ok')}
            <div class="compliance-reminder-container" style="margin-top: 15px;">
              <div class="card warning-panel" style="border-left-color: #f97316;">
                <h3>⚠️ ATTENTION: Fire Extinguisher Refilling Servicing Deadlines</h3>
                <p>The following customer site installations require cylinder refilling servicing soon or are overdue (based on the 1-year service interval from starting date):</p>
                <ul style="margin: 8px 0 0 16px; padding: 0; font-size: 13px; line-height: 1.6; color: #cbd5e1;">
                  {#each projects.filter(p => p.is_refilling_project) as p}
                    {@const status = getRefillStatus(p)}
                    {#if status.status !== 'ok'}
                      <li>
                        Project <strong>{p.name}</strong> ({p.client}) - <span class={status.class} style="display: inline-block; vertical-align: middle;">{status.text}</span>
                      </li>
                    {/if}
                  {/each}
                </ul>
              </div>
            </div>
          {/if}
        </div>
      {/if}

      <!-- Tab: Manage Projects -->
      {#if activeTab === 'manage-projects'}
        <div class="tab-pane">
          <div class="guide-box">
            <h4>🏢 Project Directory & Refill Reminder Dashboard</h4>
            <p>Register client project installations. Enable fire extinguisher refilling reminders to dynamically monitor annual servicing compliance intervals based on your starting dates.</p>
          </div>

          {#if projectError}
            <div class="alert error">{projectError}</div>
          {/if}
          {#if projectSuccess}
            <div class="alert success">{projectSuccess}</div>
          {/if}

          <div class="dashboard-body">
            <div class="card card-wide">
              <h2>Registered Project Directory</h2>
              <div class="responsive-table-container">
                <table>
                  <thead>
                    <tr>
                      <th>Project ID</th>
                      <th>Project / Site Name</th>
                      <th>Company Name</th>
                      <th>Location / Address</th>
                      <th>Starting Date</th>
                      <th>Contact Info</th>
                      <th>Refill Reminders</th>
                      <th>Supply Items</th>
                      <th style="text-align: center;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {#if projects.length === 0}
                      <tr>
                        <td colspan="9" class="text-muted" style="text-align: center; padding: 20px;">No projects registered. Create one using the form on the right!</td>
                      </tr>
                    {:else}
                      {#each projects as proj}
                        {@const refill = getRefillStatus(proj)}
                        <tr>
                          <td><span class="pill-id">{proj.id}</span></td>
                          <td class="text-bold">{proj.name}</td>
                          <td>{proj.client}</td>
                          <td>{proj.location}</td>
                          <td>{proj.starting_date || 'N/A'}</td>
                          <td>
                            {#if proj.contact_person || proj.contact_number}
                              <div style="font-size: 12px; line-height: 1.4;">
                                {#if proj.contact_person}<div><strong>Name:</strong> {proj.contact_person}</div>{/if}
                                {#if proj.contact_number}<div><strong>Ph:</strong> {proj.contact_number}</div>{/if}
                              </div>
                            {:else}
                              <span class="text-muted">N/A</span>
                            {/if}
                          </td>
                          <td>
                            <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                              {#if proj.is_refilling_project}
                                <span class="badge type-grn" style="background-color: rgba(249, 115, 22, 0.15); color: #f97316; border: 1px solid rgba(249, 115, 22, 0.3); font-size: 10px; padding: 2px 6px;">Refilling Project</span>
                              {/if}
                              {#if proj.is_refilling_reminders}
                                <span class={refill.class} style="font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 4px;">{refill.text}</span>
                              {:else}
                                <span style="font-size: 11px; color: #64748b; font-weight: 600; padding-left: 2px;">Reminders Off</span>
                              {/if}
                            </div>
                          </td>
                          <td>
                            {#if proj.supplied_items_list && proj.supplied_items_list.length > 0}
                              <div style="display: flex; flex-direction: column; gap: 4px;">
                                {#each proj.supplied_items_list as item}
                                  {#if item.sku}
                                    <div style="font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                                      <code class="code-sku" style="padding: 2px 6px;">{item.sku}</code>
                                      <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">
                                        ({formatQty(item.qty)} {items.find(i => i.sku.toLowerCase() === item.sku.toLowerCase())?.unit || 'Pcs'})
                                      </span>
                                    </div>
                                  {/if}
                                {/each}
                              </div>
                            {:else}
                              <span class="text-muted">None</span>
                            {/if}
                          </td>
                          <td style="text-align: center;">
                            <div style="display: flex; gap: 6px; justify-content: center;">
                              <button onclick={() => editProject(proj)} class="btn-op edit">Edit</button>
                              <button onclick={() => handleDeleteProject(proj.id)} class="btn-op delete">Delete</button>
                            </div>
                          </td>
                        </tr>
                      {/each}
                    {/if}
                  </tbody>
                </table>
              </div>
            </div>

            <div class="card card-narrow">
              <h2>{isEditingProject ? 'Edit Project Details' : 'Register New Project'}</h2>
              <form onsubmit={handleSaveProject} class="standard-form">
                <label>
                  Project / Site Name *
                  <input type="text" bind:value={inputProjectRecord.name} placeholder="e.g. Dhaka Office Sprinkler Retrofit" required />
                </label>

                <label>
                  Company / Client Name *
                  <input type="text" bind:value={inputProjectRecord.client_name} placeholder="e.g. Standard Chartered Bank" required />
                </label>

                <label>
                  Location / Address *
                  <input type="text" bind:value={inputProjectRecord.location} placeholder="e.g. Gulshan-2, Dhaka" required />
                </label>

                <div style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 8px;">
                  <label style="flex: 1; min-width: 140px; margin-bottom: 0;">
                    Starting Date
                    <input type="date" bind:value={inputProjectRecord.starting_date} style="margin: 0;" />
                  </label>
                  <div class="switch-container" style="margin: 0; padding: 10px 0;">
                    <label class="switch">
                      <input type="checkbox" bind:checked={inputProjectRecord.is_refilling_project} />
                      <span class="slider"></span>
                    </label>
                    <span class="switch-label-text">Refilling Project</span>
                  </div>
                  <div class="switch-container" style="margin: 0; padding: 10px 0;">
                    <label class="switch">
                      <input type="checkbox" bind:checked={inputProjectRecord.is_refilling_reminders} />
                      <span class="slider"></span>
                    </label>
                    <span class="switch-label-text">Refilling Reminders</span>
                  </div>
                </div>

                <label>
                  Contact Person Name
                  <input type="text" bind:value={inputProjectRecord.contact_person} placeholder="e.g. Mr. Rahman (Security Lead)" />
                </label>

                <label>
                  Contact Number
                  <input 
                    type="text" 
                    value={inputProjectRecord.contact_number} 
                    oninput={(e) => {
                      inputProjectRecord.contact_number = formatBDPhoneNumber(e.currentTarget.value);
                    }} 
                    placeholder="e.g. 01712-345678" 
                  />
                </label>

                <div class="switch-container" style="margin-top: 12px; margin-bottom: 12px;">
                  <label class="switch">
                    <input 
                      type="checkbox" 
                      bind:checked={inputProjectRecord.is_supply_items} 
                      onchange={() => {
                        if (inputProjectRecord.is_supply_items && (!inputProjectRecord.supplied_items_list || inputProjectRecord.supplied_items_list.length === 0)) {
                          inputProjectRecord.supplied_items_list = [{ sku: '', qty: 1 }];
                        }
                      }}
                    />
                    <span class="slider"></span>
                  </label>
                  <span class="switch-label-text">Supply Items</span>
                </div>

                {#if inputProjectRecord.is_supply_items}
                  <div style="border: 1px dashed #232a35; border-radius: 8px; padding: 12px; display: flex; flex-direction: column; gap: 12px; margin-bottom: 12px; background: rgba(18, 22, 31, 0.3);">
                    {#each inputProjectRecord.supplied_items_list as row, idx}
                      {@const rowUnit = items.find(i => i.sku.toLowerCase() === (row.sku || '').toLowerCase())?.unit || 'Pcs'}
                      <div style="border-bottom: 1px solid #1e293b; padding-bottom: 12px; margin-bottom: 4px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                          <span style="font-size: 11px; font-weight: 700; color: #f97316; text-transform: uppercase;">Item #{idx + 1}</span>
                          {#if inputProjectRecord.supplied_items_list.length > 1}
                            <button type="button" onclick={() => removeSupplyItemRow(idx)} class="btn-op delete" style="padding: 2px 8px; font-size: 11px;">Remove</button>
                          {/if}
                        </div>
                        <label>
                          Select/Type Supply Item SKU *
                          <input 
                            type="text" 
                            list="inventory-items-datalist" 
                            bind:value={inputProjectRecord.supplied_items_list[idx].sku} 
                            onchange={(e) => handleCheckInventoryItem(idx, e)}
                            placeholder="e.g. FM200-CYL-120L" 
                            required
                          />
                        </label>
                        <div class="form-row-2" style="margin-top: 10px;">
                          <label>
                            Supply Quantity *
                            <input 
                              type="number" 
                              step="1" 
                              bind:value={inputProjectRecord.supplied_items_list[idx].qty} 
                              min="1" 
                              required 
                            />
                          </label>
                          <label>
                            Item Unit (Auto)
                            <input 
                              type="text" 
                              value={rowUnit} 
                              readonly 
                              disabled 
                              style="background-color: #12161f; color: #94a3b8; cursor: not-allowed; font-size: 13px;" 
                            />
                          </label>
                        </div>
                      </div>
                    {/each}

                    <button type="button" onclick={addSupplyItemRow} class="btn-op edit" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 8px; font-size: 12px; font-weight: 700;">
                      ➕ Add Another Item
                    </button>
                  </div>
                  
                  <datalist id="inventory-items-datalist">
                    {#each items as item}
                      <option value={item.sku}>{item.name} ({item.unit})</option>
                    {/each}
                  </datalist>
                {/if}

                <div class="form-actions">
                  <button type="submit" class="btn btn-primary">{isEditingProject ? 'Save Changes' : 'Register Project'}</button>
                  {#if isEditingProject}
                    <button type="button" onclick={resetProjectForm} class="btn btn-cancel">Cancel</button>
                  {/if}
                </div>
              </form>
            </div>
          </div>
        </div>
      {/if}

      <!-- Tab 2: Make Invoice (Direct simple billing form) -->
      {#if activeTab === 'make-invoice'}
        <div class="tab-pane">
          <div class="guide-box">
            <h4>✍ Simple Invoice Generator</h4>
            <p>Generate a direct bill for customers. Type the customer name, choose the items sold, and specify whether NBR VAT Tax should be added. Invoice numbers are generated automatically.</p>
          </div>

          <div class="invoice-generator-container">
            <div class="card invoice-form-card">
              <h2>New Invoice Details</h2>
              {#if simpleBillingError}
                <div class="alert error">{simpleBillingError}</div>
              {/if}
              {#if simpleBillingSuccess}
                <div class="alert success">{simpleBillingSuccess}</div>
              {/if}

              <form onsubmit={handleCreateSimpleInvoice} class="standard-form">
                <div class="form-row-2">
                  <label>
                    Invoice Number (Automatic)
                    <input type="text" value={autoInvoiceNumber} readonly class="bg-dark-disabled" />
                  </label>
                  <label>
                    Customer / Client Name
                    <input type="text" bind:value={inputSimpleBilling.customer_name} placeholder="e.g. Dhaka Central Hospital" required />
                  </label>
                </div>

                <div class="form-row-3">
                  <label>
                    Select Product from Inventory
                    <select bind:value={inputSimpleBilling.sku} onchange={handleSkuChange}>
                      {#each items as item}
                        <option value={item.sku}>{item.sku} - {item.name}</option>
                      {/each}
                    </select>
                  </label>
                  <label>
                    Quantity Sold
                    <input type="number" bind:value={inputSimpleBilling.qty} min="1" required />
                  </label>
                  <label>
                    Unit Price (BDT)
                    <input type="number" bind:value={inputSimpleBilling.unit_price} min="1" required />
                  </label>
                </div>

                <div class="vat-option-toggle">
                  <label class="checkbox-label">
                    <input type="checkbox" bind:checked={inputSimpleBilling.is_vat_free} />
                    <span>This sale is VAT Free (Tax Exempt)</span>
                  </label>
                </div>

                <div class="calculation-summary-box">
                  <h3>Price Calculation Summary</h3>
                  <div class="calc-row">
                    <span>Subtotal:</span>
                    <strong>{formatMoney(inputSimpleBilling.qty * inputSimpleBilling.unit_price)}</strong>
                  </div>
                  <div class="calc-row">
                    <span>VAT Tax (15%):</span>
                    <strong class={inputSimpleBilling.is_vat_free ? 'color-disabled' : 'color-orange'}>
                      {inputSimpleBilling.is_vat_free ? '0 BDT (Exempt)' : formatMoney((inputSimpleBilling.qty * inputSimpleBilling.unit_price) * 0.15)}
                    </strong>
                  </div>
                  <div class="calc-row total-row">
                    <span>Grand Total Billed:</span>
                    <span class="grand-total-amount">
                      {formatMoney((inputSimpleBilling.qty * inputSimpleBilling.unit_price) * (inputSimpleBilling.is_vat_free ? 1.0 : 1.15))}
                    </span>
                  </div>
                </div>

                <button type="submit" class="btn btn-primary">Generate & Issue Invoice</button>
              </form>
            </div>
          </div>
        </div>
      {/if}

      <!-- Tab 3: Product Directory CRUD -->
      {#if activeTab === 'products'}
        <div class="tab-pane">
          <div class="guide-box">
            <h4>🛠 Add an Item</h4>
            <p>Use this section to add new fire protection equipment, adjust restock levels, or update descriptions. You cannot delete items that have been delivered to project sites in the past.</p>
          </div>

          <div class="dashboard-body">
            <!-- Product Catalog Table -->
            <div class="card card-wide">
              <h2>Equipment & Part Directory</h2>
              {#if productError}
                <div class="alert error">{productError}</div>
              {/if}
              {#if productSuccess}
                <div class="alert success">{productSuccess}</div>
              {/if}

              <div class="responsive-table-container">
                <table>
                  <thead>
                    <tr>
                      <th>Item Code</th>
                      <th>Product Name</th>
                      <th>Tracking Method</th>
                      <th>Daily Used Rate</th>
                      <th>Supplier Days</th>
                      <th>Restock Level</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {#each currentStock as item}
                      <tr>
                        <td><code class="code-sku">{item.sku}</code></td>
                        <td>{item.name}</td>
                        <td><span class="badge category">{item.type}</span></td>
                        <td>{formatQty(item.average_consumption_rate)} {item.unit || 'Pcs'}/day</td>
                        <td>{item.lead_time_days} Days</td>
                        <td class="color-blue">{formatQty(item.average_consumption_rate * item.lead_time_days)} {item.unit || 'Pcs'}</td>
                        <td class="operations-cell">
                          <button onclick={() => editProduct(item)} class="btn-op edit">Modify</button>
                          <button onclick={() => handleDeleteProduct(item.id)} class="btn-op delete">Remove</button>
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Form: Add/Edit Product -->
            <div class="card card-narrow">
              <h2>{isEditingProduct ? 'Modify Product Details' : 'Register New Equipment'}</h2>
              <form onsubmit={handleSaveProduct} class="standard-form">
                <label>
                  Item Code / Part Number
                  <input type="text" bind:value={inputProduct.sku} placeholder="e.g. SD-ADR-908" required readonly={isEditingProduct} />
                </label>
                <label>
                  Equipment Name
                  <input type="text" bind:value={inputProduct.name} placeholder="e.g. Optical Smoke Sensor" required />
                </label>
                <label>
                  How is this item tracked?
                  <select bind:value={inputProduct.type} disabled={isEditingProduct}>
                    <option value="Standard">Standard Inventory (Bulk counts)</option>
                    <option value="Serialized">Serialized Asset (Unique Serial Numbers)</option>
                    <option value="BatchManaged">Batch/Lot Managed (Lot Expire dates)</option>
                    <option value="KitAssembly">Kit Assembly (Bill of Materials package)</option>
                  </select>
                </label>
                <label>
                  Unit of Measurement
                  <select bind:value={inputProduct.unit}>
                    <option value="Pcs">Pcs (Pieces)</option>
                    <option value="Mtrs">Mtrs (Meters)</option>
                    <option value="Sets">Sets (Complete Sets)</option>
                    <option value="Kits">Kits (Service Kits)</option>
                    <option value="Kgs">Kgs (Kilograms)</option>
                  </select>
                </label>
                {#if !isEditingProduct}
                  <label>
                    Product Quantity (Initial Stock)
                    <input type="number" step="1" bind:value={inputProduct.initial_quantity} min="0" required />
                  </label>
                {/if}
                <label>
                  Selling Price (BDT)
                  <input type="number" step="0.01" bind:value={inputProduct.selling_price} min="0" required />
                </label>
                <label>
                  Purchase Price (BDT)
                  <input type="number" step="0.01" bind:value={inputProduct.purchase_price} min="0" required />
                </label>

                <div class="form-actions">
                  <button type="submit" class="btn btn-primary">{isEditingProduct ? 'Save Changes' : 'Add Item'}</button>
                  {#if isEditingProduct}
                    <button type="button" onclick={resetProductForm} class="btn btn-cancel">Cancel</button>
                  {/if}
                </div>
              </form>
            </div>
          </div>
        </div>
      {/if}

      <!-- Tab 4: Sales Management -->
      {#if activeTab === 'sales'}
        <div class="tab-pane">
          <div class="guide-box">
            <h4>💼 Prices & Quotations History</h4>
            <p>This tab maintains negotiation history. Quotations are locked once sent. Any adjustments create a newer version of the quotation for history audit tracking.</p>
          </div>

          <div class="dashboard-body">
            <!-- Quotation Versions negotiation tree -->
            <div class="card card-wide">
              <h2>Customer Project Price Negotiations</h2>
              {#each quotes as quote}
                <div class="quote-header">
                  <h3>Quotation Code: {quote.id}</h3>
                  <span class="project-name">Project Site: {quote.project}</span>
                </div>
                <div class="negotiation-tree">
                  {#each quote.versions as ver}
                    <div class="tree-node" class:approved={ver.status === 'Approved'} class:rejected={ver.status === 'Rejected'}>
                      <div class="node-ver">v{ver.version}</div>
                      <div class="node-details">
                        <div class="node-meta">
                          <span>Total Proposed: <strong>{formatMoney(ver.total)}</strong></span>
                          <span>NBR VAT Tax (15%): <strong>{formatMoney(ver.vat)}</strong></span>
                          <span class="badge status-{ver.status.toLowerCase()}">{ver.status}</span>
                        </div>
                        <p class="notes">✍ Client Negotiation Log: {ver.notes} ({ver.date})</p>
                      </div>
                    </div>
                  {/each}
                </div>
              {/each}
            </div>

            <!-- Negotiation Control -->
            <div class="card card-narrow">
              <h2>Issue Revised Quotation</h2>
              <form onsubmit={(e) => { e.preventDefault(); handleNegotiateQuote(); }} class="standard-form">
                <label>
                  Select Active Quotation
                  <select bind:value={inputQuoteNegotiation.quote_id}>
                    {#each quotes as q}
                      <option value={q.id}>{q.id} ({q.project})</option>
                    {/each}
                  </select>
                </label>
                <label>
                  New Price Offer (Net BDT)
                  <input type="number" bind:value={inputQuoteNegotiation.grand_total} />
                </label>
                <label>
                  Revision Notes (Reason for change)
                  <textarea bind:value={inputQuoteNegotiation.notes} placeholder="e.g. Revised after client requested pipe discount"></textarea>
                </label>
                <button type="submit" class="btn btn-primary">Create Revised Version</button>
              </form>
            </div>
          </div>
        </div>
      {/if}

      <!-- Tab 5: Inventory Control -->
      {#if activeTab === 'inventory'}
        <div class="tab-pane">
          <div class="guide-box">
            <h4>📦 Deliveries & Stock Movements History</h4>
            <p>Every time stock arrives from a supplier or leaves for a project site, it must be recorded here. Direct balance overrides are blocked to prevent accounting gaps.</p>
          </div>

          <div class="dashboard-body">
            <!-- Double-Entry Stock Ledger Log -->
            <div class="card card-wide">
              <h2>Stock Movement History Log (Audit Log)</h2>
              <div class="responsive-table-container">
                <table>
                  <thead>
                    <tr>
                      <th>Log Code</th>
                      <th>Item Code</th>
                      <th>Direction</th>
                      <th>Moved From</th>
                      <th>Moved To</th>
                      <th>Quantity</th>
                      <th>Special Tracking</th>
                    </tr>
                  </thead>
                  <tbody>
                    {#each stockLedger as entry}
                      <tr>
                        <td><code>{entry.id}</code></td>
                        <td><code class="code-sku">{entry.item_sku}</code></td>
                        <td><span class="badge type-{entry.type.toLowerCase() === 'stock in' ? 'grn' : 'dispatch'}">{entry.type}</span></td>
                        <td>{entry.from}</td>
                        <td>{entry.to}</td>
                        <td class="text-bold">{formatQty(entry.qty)} Units</td>
                        <td>
                          {#if entry.serial}
                            <span class="pill-id">Serial: {entry.serial}</span>
                          {:else}
                            <span class="pill-id">Batch Lock</span>
                          {/if}
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Post Stock Transaction -->
            <div class="card card-narrow">
              <h2>Record New Stock Entry</h2>
              {#if ledgerError}
                <div class="alert error">{ledgerError}</div>
              {/if}
              {#if ledgerSuccess}
                <div class="alert success">{ledgerSuccess}</div>
              {/if}
              <form onsubmit={(e) => { e.preventDefault(); handlePostLedger(); }} class="standard-form">
                <label>
                  Action Direction
                  <select bind:value={inputLedger.type}>
                    <option value="Stock In">Receive Stock from Supplier (Stock In)</option>
                    <option value="Transfer">Move between Warehouses</option>
                    <option value="Stock Out">Deliver to Client Project (Stock Out)</option>
                  </select>
                </label>
                <label>
                  Item to Move
                  <select bind:value={inputLedger.sku}>
                    {#each items as i}
                      <option value={i.sku}>{i.sku} ({i.name})</option>
                    {/each}
                  </select>
                </label>
                <label>
                  Sender Location (From)
                  <input type="text" bind:value={inputLedger.from} />
                </label>
                <label>
                  Recipient Location (To)
                  <input type="text" bind:value={inputLedger.to} />
                </label>
                <label>
                  Quantity
                  <input type="number" bind:value={inputLedger.qty} />
                </label>
                {#if items.find(i => i.sku === inputLedger.sku)?.type === 'Serialized'}
                  <label class="highlight">
                    Manufacturer Serial Number (Required for panels/pumps)
                    <input type="text" bind:value={inputLedger.serial} placeholder="e.g. SN-NFX-9082" />
                  </label>
                {/if}
                {#if items.find(i => i.sku === inputLedger.sku)?.type === 'BatchManaged'}
                  <label class="highlight">
                    Cylinder Batch Lot
                    <select bind:value={inputLedger.batch}>
                      {#each batches as b}
                        <option value={b.batch}>{b.batch} (Hydro deadline: {b.hydro_due})</option>
                      {/each}
                    </select>
                  </label>
                {/if}
                <button type="submit" class="btn btn-primary">Save Stock Entry</button>
              </form>
            </div>
          </div>
        </div>
      {/if}

      <!-- Tab 6: Customer Invoices List -->
      {#if activeTab === 'billing'}
        <div class="tab-pane">
          <div class="guide-box">
            <h4>🧾 Invoices & Outstanding Bills</h4>
            <p>Below is a log of all invoices that have been issued to clients. You can click on the `Make Invoice` tab in the sidebar to generate a new one.</p>
          </div>

          {#if driveSuccess}
            <div class="alert success" style="margin-bottom: 20px;">{driveSuccess}</div>
          {/if}
          {#if driveError}
            <div class="alert error" style="margin-bottom: 20px;">{driveError}</div>
          {/if}

          <div class="dashboard-body">
            <!-- Invoices List -->
            <div class="card card-wide">
              <h2>Issued Client Invoices</h2>
              <div class="responsive-table-container">
                <table>
                  <thead>
                    <tr>
                      <th>Invoice Number</th>
                      <th>Customer / Client Name</th>
                      <th>Billing Reference</th>
                      <th>Quantity Billed</th>
                      <th>Unit Price</th>
                      <th>NBR VAT Mushak-6.3</th>
                      <th>Total Billed (Inc. VAT)</th>
                      <th>Status</th>
                      <th>Payment Due</th>
                      <th style="text-align: center;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {#each invoices as inv}
                      <tr>
                        <td><code>{inv.id}</code></td>
                        <td class="text-bold">{inv.customer_name || (inv.project.startsWith('Direct Sale: ') ? inv.project.substring(13) : inv.project)}</td>
                        <td><code>{inv.challan}</code></td>
                        <td>{formatQty(inv.qty)} Units</td>
                        <td>{formatMoney(inv.price)}</td>
                        <td><span class="pill-vat">{inv.vat_challan}</span></td>
                        <td class="text-bold">{formatMoney(inv.qty * inv.price * (inv.vat_challan === 'VAT Free (Exempt)' ? 1.0 : 1.15))}</td>
                        <td><span class="badge status-{inv.status.toLowerCase()}">{inv.status}</span></td>
                        <td>{inv.due}</td>
                        <td style="text-align: center;">
                          <div style="display: inline-flex; gap: 8px; justify-content: center; width: 100%;">
                            <button onclick={() => openInvoicePrint(inv)} class="btn-op edit" style="min-width: auto; padding: 6px 12px !important; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">🖨 Print</button>
                            <button 
                              type="button" 
                              onclick={() => handleDirectDriveUpload(inv)} 
                              class="btn" 
                              style="background-color: #0f9d58; color: white; border: none; border-radius: 4px; min-width: auto; padding: 6px 12px !important; display: inline-flex; align-items: center; justify-content: center; gap: 4px; font-size: 13px !important; cursor: pointer;"
                              disabled={isUploadingToDrive}
                            >
                              📁 GDrive
                            </button>
                          </div>
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Total A/R summary card on side -->
            <div class="card card-narrow">
              <h2>Total Accounts Receivable</h2>
              <div class="tax-estimation">
                <span>Direct & Project Outstanding Balance</span>
                <strong>{formatMoney(invoices.filter(i => i.status === 'Unpaid').reduce((sum, inv) => sum + (inv.qty * inv.price * (inv.vat_challan === 'VAT Free (Exempt)' ? 1.0 : 1.15)), 0))}</strong>
                <p>Currently tracked under local SQLite ledger.</p>
              </div>
            </div>

            <!-- Google Drive Settings card on side -->
            <div class="card card-narrow" style="margin-top: 15px;">
              <h2>📁 Google Drive Settings</h2>
              <div class="standard-form">
                <p style="font-size: 11px; line-height: 1.4; color: #94a3b8; margin: 0 0 10px 0;">
                  Save invoices directly to your Google Drive! Enter your Web Client ID from your Google Cloud Console Credentials panel to enable this feature.
                </p>
                <label style="font-size: 12px;">
                  Google OAuth Client ID
                  <input type="text" bind:value={googleClientId} placeholder="e.g. 123456-abc.apps.googleusercontent.com" style="font-size: 13px !important; padding: 8px !important;" />
                </label>
                <label style="font-size: 12px; margin-top: 6px;">
                  Target Gmail Account (Auto-fill)
                  <input type="email" bind:value={googleLoginHint} placeholder="e.g. redshieldsafety@gmail.com" style="font-size: 13px !important; padding: 8px !important;" />
                </label>
                <button type="button" onclick={saveGoogleClientId} class="btn btn-primary" style="width: 100%; min-height: 38px !important; padding: 6px 12px !important; font-size: 13px !important;">
                  Save Credentials
                </button>
              </div>
            </div>
          </div>
        </div>
      {/if}

      <!-- Tab 7: Watch Inventory Custom Data Table -->
      {#if activeTab === 'watch-inventory'}
        <div class="tab-pane">
          <div class="guide-box">
            <h4>👀 Watch Inventory Directory</h4>
            <p>Live catalog monitoring showing current physical stock counts, contract purchase rates, standard customer selling prices, and quick procurement dispatch triggers.</p>
          </div>

          {#if productSuccess}
            <div class="alert alert-success" style="margin-bottom: 20px;">{productSuccess}</div>
          {/if}

          <div class="dashboard-body">
            <!-- Watch Inventory Custom Table -->
            <div class="card card-wide">
              <h2>Watch Inventory Catalog</h2>
              <div class="responsive-table-container">
                <table>
                  <thead>
                    <tr>
                      <th>Item Code</th>
                      <th>Equipment Name</th>
                      <th>Unit</th>
                      <th>Stock Level</th>
                      <th>Purchase Price</th>
                      <th>Selling Price</th>
                      <th style="text-align: center;">Reorder Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {#each currentStock as item}
                      <tr>
                        <td><code>{item.sku}</code></td>
                        <td class="text-bold">{item.name}</td>
                        <td><span class="pill-id">{item.unit || 'Pcs'}</span></td>
                        <td style="vertical-align: middle;">
                          <div style="display: flex; align-items: center; gap: 8px;">
                            {#if editingStockSku === item.sku}
                              <input 
                                type="number" 
                                bind:value={tempStockValue} 
                                style="width: 65px; padding: 4px 6px; border-radius: 4px; background: #1e293b; color: #fff; border: 1px solid #f97316; font-size: 13px;" 
                                min="0"
                                required
                              />
                              <button 
                                type="button" 
                                onclick={() => {
                                  updateStockLevel(item, tempStockValue);
                                  editingStockSku = '';
                                }} 
                                class="btn-op edit" 
                                style="padding: 4px 8px; background-color: #10b981; color: #fff; font-size: 11px;"
                              >
                                Save
                              </button>
                              <button 
                                type="button" 
                                onclick={() => editingStockSku = ''} 
                                class="btn-op delete" 
                                style="padding: 4px 8px; font-size: 11px;"
                              >
                                Cancel
                              </button>
                            {:else}
                              <button 
                                type="button" 
                                onclick={() => updateStockLevel(item, Math.max(0, item.stock - 1))} 
                                class="btn-op delete" 
                                style="padding: 2px 8px; font-weight: 800; font-size: 14px; border-radius: 4px; line-height: 1;"
                                title="Decrease Stock by 1"
                              >
                                −
                              </button>
                              
                              <span 
                                class="text-bold" 
                                style="font-size: 15px; min-width: 24px; text-align: center; cursor: pointer; border-bottom: 1px dashed #475569;"
                                onclick={() => {
                                  editingStockSku = item.sku;
                                  tempStockValue = Math.round(item.stock);
                                }}
                                title="Click to edit custom stock amount"
                              >
                                {formatQty(item.stock)}
                              </span>

                              <button 
                                type="button" 
                                onclick={() => updateStockLevel(item, item.stock + 1)} 
                                class="btn-op edit" 
                                style="padding: 2px 8px; font-weight: 800; font-size: 14px; border-radius: 4px; line-height: 1;"
                                title="Increase Stock by 1"
                              >
                                +
                              </button>

                              {#if item.stock <= (item.reorder_point || (item.average_consumption_rate * item.lead_time_days))}
                                <span class="badge danger">Low Stock</span>
                              {:else}
                                <span class="badge success">Safe</span>
                              {/if}
                            {/if}
                          </div>
                        </td>
                        <td>{formatMoney(item.purchase_price || 0)}</td>
                        <td>{formatMoney(item.selling_price || 0)}</td>
                        <td style="text-align: center;">
                          <button 
                            type="button" 
                            onclick={() => sendRestockReminder(item.sku, item.name)} 
                            class="btn-op {restockReminders[item.sku] ? 'btn-remind-sent' : 'edit'}"
                            style="min-width: 140px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;"
                          >
                            {#if restockReminders[item.sku]}
                              ✔ Reminder Sent
                            {:else}
                              🔔 Restock Reminder
                            {/if}
                          </button>
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      {/if}

      <!-- Tab 8: Client Directory -->
      {#if activeTab === 'client-contacts'}
        <div class="tab-pane">
          <div class="guide-box">
            <h4>👥 Client Directory</h4>
            <p>Unified directory of all registered clients, contact persons, contact numbers, project locations, and service contract types synced from the live database.</p>
          </div>

          <div class="dashboard-body">
            <div class="card card-wide">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                <h2 style="margin: 0;">Client Directory</h2>
                <div style="display: flex; gap: 8px; width: 100%; max-width: 400px;">
                  <input 
                    type="text" 
                    placeholder="Search by Company, Customer Name, Phone, Location..." 
                    bind:value={clientSearchQuery} 
                    style="margin: 0; padding: 10px 14px; border-radius: 4px; border: 1px solid #232a35; background: #12161f; color: #fff; width: 100%; font-size: 13px;"
                  />
                </div>
              </div>

              <div class="responsive-table-container">
                <table>
                  <thead>
                    <tr>
                      <th>Company Name</th>
                      <th>Customer Name</th>
                      <th>Phone Number</th>
                      <th>Location / Address</th>
                      <th>Project Type</th>
                    </tr>
                  </thead>
                  <tbody>
                    {#each filteredClients as proj}
                      <tr>
                        <td class="text-bold" style="color: #f97316;">{proj.client}</td>
                        <td style="font-weight: 600;">
                          {#if proj.contact_person}
                            👤 {proj.contact_person}
                          {:else}
                            <span class="text-muted">—</span>
                          {/if}
                        </td>
                        <td>
                          {#if proj.contact_number}
                            <a href="tel:{proj.contact_number}" style="color: #3b82f6; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                              📞 {proj.contact_number}
                            </a>
                          {:else}
                            <span class="text-muted">—</span>
                          {/if}
                        </td>
                        <td>
                          <span style="font-size: 12px; color: #f8fafc; display: inline-flex; align-items: center; gap: 4px;">
                            📍 {proj.location}
                          </span>
                        </td>
                        <td>
                          <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                            {#if proj.is_refilling_project}
                              <span class="badge" style="background-color: rgba(249, 115, 22, 0.15); color: #f97316; border: 1px solid rgba(249, 115, 22, 0.3); font-size: 10px; padding: 2px 6px; font-weight: 600;">Refilling Project</span>
                            {/if}
                            {#if proj.is_refilling_reminders}
                              <span class="badge" style="background-color: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); font-size: 10px; padding: 2px 6px; font-weight: 600;">Reminders Active</span>
                            {:else}
                              <span style="font-size: 11px; color: #64748b; font-weight: 600; padding-left: 2px;">Reminders Off</span>
                            {/if}
                          </div>
                        </td>
                      </tr>
                    {:else}
                      <tr>
                        <td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;">
                          No matching clients found.
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      {/if}
    </section>

    {#if showInvoicePrintModal && selectedInvoiceForPrint}
      <div class="modal-backdrop" onclick={() => showInvoicePrintModal = false}>
        <div class="modal-card user-guide-modal" onclick={(e) => e.stopPropagation()} style="max-width: 800px !important;">
          <div class="modal-header no-print">
            <h2>🧾 Invoice Receipt Preview</h2>
            <button type="button" onclick={() => showInvoicePrintModal = false} class="btn-close-modal" aria-label="Close Preview">✕</button>
          </div>

          {#if driveSuccess}
            <div class="alert success no-print" style="margin: 15px 30px 0 30px;">{driveSuccess}</div>
          {/if}
          {#if driveError}
            <div class="alert error no-print" style="margin: 15px 30px 0 30px;">{driveError}</div>
          {/if}

          <div class="modal-body invoice-printable-area" style="background-color: #ffffff; color: #0f172a; padding: 30px; border-radius: 6px;">
            <!-- Invoice Header -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 2px solid #cbd5e1; padding-bottom: 15px;">
              <div>
                <h1 style="margin: 0; font-size: 24px; color: #1e3a8a; display: flex; align-items: center; gap: 8px;">🔥 HFST Fire Safety</h1>
                <p style="margin: 4px 0 0 0; font-size: 13px; color: #475569;">House-44, Road-12, Sector-3, Uttara, Dhaka</p>
                <p style="margin: 2px 0 0 0; font-size: 13px; color: #475569;">Ph: +8801712345678 | Email: billing@hfsterp.com</p>
              </div>
              <div style="text-align: right;">
                <h2 style="margin: 0; font-size: 20px; color: #c2410c; letter-spacing: 1px;">BILLING RECEIPT</h2>
                <p style="margin: 4px 0 0 0; font-size: 12px; color: #475569;"><strong>Invoice No:</strong> {selectedInvoiceForPrint.id}</p>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: #475569;"><strong>Date:</strong> {selectedInvoiceForPrint.date}</p>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: #475569;"><strong>Due Date:</strong> {selectedInvoiceForPrint.due}</p>
              </div>
            </div>

            <!-- Bill To -->
            <div style="margin-bottom: 25px; display: flex; justify-content: space-between;">
              <div>
                <h3 style="margin: 0 0 6px 0; font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Bill To:</h3>
                <p style="margin: 0; font-size: 15px; font-weight: 700; color: #0f172a;">{selectedInvoiceForPrint.customer_name || (selectedInvoiceForPrint.project.startsWith('Direct Sale: ') ? selectedInvoiceForPrint.project.substring(13) : selectedInvoiceForPrint.project)}</p>
                <p style="margin: 4px 0 0 0; font-size: 13px; color: #475569;"><strong>Billing Reference:</strong> {selectedInvoiceForPrint.challan}</p>
              </div>
              <div style="text-align: right;">
                <h3 style="margin: 0 0 6px 0; font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Status:</h3>
                <span class="badge status-{selectedInvoiceForPrint.status.toLowerCase()}" style="display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 700; border-radius: 4px;">{selectedInvoiceForPrint.status}</span>
              </div>
            </div>

            <!-- Items Table -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 13px;">
              <thead>
                <tr style="border-bottom: 2px solid #e2e8f0; background-color: #f8fafc;">
                  <th style="padding: 10px; text-align: left; color: #475569; font-weight: 700;">Description</th>
                  <th style="padding: 10px; text-align: right; color: #475569; font-weight: 700; width: 80px;">Qty</th>
                  <th style="padding: 10px; text-align: right; color: #475569; font-weight: 700; width: 120px;">Unit Price</th>
                  <th style="padding: 10px; text-align: right; color: #475569; font-weight: 700; width: 140px;">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                  <td style="padding: 12px 10px; color: #0f172a;">
                    Fire Safety Equipment supply & services (Reference: {selectedInvoiceForPrint.challan})
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Compliance: {selectedInvoiceForPrint.vat_challan}</div>
                  </td>
                  <td style="padding: 12px 10px; text-align: right; color: #0f172a;">{formatQty(selectedInvoiceForPrint.qty)}</td>
                  <td style="padding: 12px 10px; text-align: right; color: #0f172a;">{formatMoney(selectedInvoiceForPrint.price)}</td>
                  <td style="padding: 12px 10px; text-align: right; color: #0f172a; font-weight: 700;">{formatMoney(selectedInvoiceForPrint.qty * selectedInvoiceForPrint.price)}</td>
                </tr>
              </tbody>
            </table>

            <!-- Totals -->
            <div style="display: flex; justify-content: flex-end; margin-bottom: 30px;">
              <div style="width: 280px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9; color: #475569;">
                  <span>Subtotal:</span>
                  <span style="font-weight: 700; color: #0f172a;">{formatMoney(selectedInvoiceForPrint.qty * selectedInvoiceForPrint.price)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9; color: #475569;">
                  <span>NBR VAT (15%):</span>
                  <span style="font-weight: 700; color: #0f172a;">{formatMoney(selectedInvoiceForPrint.vat_challan === 'VAT Free (Exempt)' ? 0 : (selectedInvoiceForPrint.qty * selectedInvoiceForPrint.price * 0.15))}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0; font-size: 15px; font-weight: 800; color: #1e3a8a; border-top: 2px double #cbd5e1; margin-top: 4px;">
                  <span>Total Payable:</span>
                  <span>{formatMoney(selectedInvoiceForPrint.qty * selectedInvoiceForPrint.price * (selectedInvoiceForPrint.vat_challan === 'VAT Free (Exempt)' ? 1.0 : 1.15))}</span>
                </div>
              </div>
            </div>

            <!-- Footer terms -->
            <div style="border-top: 1px solid #cbd5e1; padding-top: 15px; text-align: center; font-size: 11px; color: #64748b; line-height: 1.5;">
              <p style="margin: 0;">Certified under BNBC safety codes & standard fire protection guidelines.</p>
              <p style="margin: 2px 0 0 0; font-weight: 700; color: #475569;">Thank you for your business!</p>
            </div>
          </div>

          <div class="modal-footer no-print" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <div style="font-size: 11px; color: #94a3b8; text-align: left; max-width: 45%;">
              💡 <strong>Tip:</strong> Save to Google Drive directly using the API button, or via Print -> Save as PDF.
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
              <button 
                type="button" 
                onclick={() => handleBackupToGoogleDrive(selectedInvoiceForPrint)} 
                class="btn" 
                style="background-color: #0f9d58; color: white; display: inline-flex; align-items: center; gap: 6px; min-height: 38px !important; padding: 6px 12px !important; font-size: 13px !important;"
                disabled={isUploadingToDrive}
              >
                {#if isUploadingToDrive}
                  ⌛ Uploading...
                {:else}
                  📁 Save to Google Drive
                {/if}
              </button>
              <button onclick={() => window.print()} class="btn btn-primary" style="min-height: 38px !important; padding: 6px 12px !important; font-size: 13px !important;">🖨 Print / PDF</button>
              <button onclick={() => showInvoicePrintModal = false} class="btn btn-cancel" style="min-height: 38px !important; padding: 6px 12px !important; font-size: 13px !important;">Close</button>
            </div>
          </div>
        </div>
      </div>
    {/if}

    {#if showUserGuideModal}
      <div class="modal-backdrop" onclick={() => showUserGuideModal = false}>
        <div class="modal-card user-guide-modal" onclick={(e) => e.stopPropagation()}>
          <div class="modal-header">
            <h2>📖 HFST ERP User Guide & Documentation</h2>
            <button type="button" onclick={() => showUserGuideModal = false} class="btn-close-modal" aria-label="Close Guide">✕</button>
          </div>
          <div class="modal-body scrollable-guide-content">
            <h3>1. Why Fire Safety Compliance Matters</h3>
            <p>Under fire safety regulations—specifically the <strong>NFPA 10 Standard for Portable Fire Extinguishers</strong> and the <strong>Bangladesh National Building Code (BNBC)</strong>—safety equipment is subject to strict inspections.</p>
            <div class="modal-alert-box">
              <strong>⚠️ Hydrostatic Testing:</strong> Over time, fire safety cylinders (such as CO₂ or Clean Agent gas canisters) hold gas under extreme pressure. If a cylinder's metal shell weakens, it can rupture and cause injury. Hydrostatic testing is a mandatory high-pressure water test designed to check the cylinder's strength.
            </div>
            <p>The ERP automatically scans the manufacture dates of your cylinder batches. If a batch is nearing its hydrostatic test due date, the system alerts you in the <strong>Soft Reminder</strong> warning card at the bottom right of the dashboard. Do not dispatch, sell, or install cylinders from an flagged lot until they undergo pressure-testing and have their certification date renewed.</p>

            <h3>2. Dashboard (Summary Page)</h3>
            <ul>
              <li><strong>Active Project Sites:</strong> The number of ongoing installation jobs where HFST equipment is currently deployed.</li>
              <li><strong>Total Stock Items:</strong> The total count of all units currently sitting in your Uttara Depot.</li>
              <li><strong>Total Money Owed by Customers:</strong> The sum of all unpaid customer bills that have been dispatched but not yet cleared.</li>
              <li><strong>Safety Tests Due:</strong> The count of extinguisher batches that require immediate hydrostatic pressure testing.</li>
              <li><strong>Warehouse Inventory Table:</strong> Shows your current stock level, unit category, and safety restock alerts (Red if stock drops below safe levels).</li>
            </ul>

            <h3>3. How to Add an Item (Inventory Setup)</h3>
            <p>To register a brand-new safety product in the ERP catalog, click the <strong>🛠 Add an Item</strong> tab and fill in details such as SKU, Display Name, Unit (Pcs, Mtrs, Sets, Kits, Kgs), daily consumption rate, and supplier lead time.</p>

            <h3>4. Prices & Quotations (Quotes & Revisions)</h3>
            <p>This tab lets you create official price estimates for clients. The system automatically appends a <strong>15% National VAT</strong> rate to all quotes. If a customer requests a price update, you can generate a new <strong>Revision Version</strong> to preserve the negotiation audit trail.</p>

            <h3>5. Deliveries & Stock (Warehouse Log)</h3>
            <p>Use this digital logbook to record stock movements. Choose <strong>Stock Add</strong> (for factory shipments) or <strong>Stock Deduct</strong> (for job site dispatch). Every stock entry is instantly logged in the historical ledger.</p>

            <h3>6. How to Create an Invoice (Challans & Billing)</h3>
            <p>To invoice a customer, click the <strong>✍ Make Invoice</strong> tab, select the project and product, enter the quantity, unit price, VAT status, and Challan reference number. Click <strong>Generate & Issue Invoice</strong> to create the bill, deduct stock, and display a print-ready challan pass.</p>

            <h3>7. Theme Settings</h3>
            <p>Click the <strong>☀️ Light Mode / 🌙 Dark Mode</strong> button next to the system status badge to switch visual themes at any time. Dark mode is optimized for dark warehouses, while light mode is designed for bright offices.</p>
          </div>
        </div>
      </div>
    {/if}

    <!-- Invisible container for silent PDF export -->
    {#if activeInvoiceForSilentExport}
      <div style="position: absolute; left: -9999px; top: -9999px; width: 800px; background-color: #ffffff; color: #0f172a; padding: 30px;" id="silent-invoice-export-container">
        <!-- Invoice Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 2px solid #cbd5e1; padding-bottom: 15px;">
          <div>
            <h1 style="margin: 0; font-size: 24px; color: #1e3a8a; display: flex; align-items: center; gap: 8px;">🔥 HFST Fire Safety</h1>
            <p style="margin: 4px 0 0 0; font-size: 13px; color: #475569;">House-44, Road-12, Sector-3, Uttara, Dhaka</p>
            <p style="margin: 2px 0 0 0; font-size: 13px; color: #475569;">Ph: +8801712345678 | Email: billing@hfsterp.com</p>
          </div>
          <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 20px; color: #c2410c; letter-spacing: 1px;">BILLING RECEIPT</h2>
            <p style="margin: 4px 0 0 0; font-size: 12px; color: #475569;"><strong>Invoice No:</strong> {activeInvoiceForSilentExport.id}</p>
            <p style="margin: 2px 0 0 0; font-size: 12px; color: #475569;"><strong>Date:</strong> {activeInvoiceForSilentExport.date}</p>
            <p style="margin: 2px 0 0 0; font-size: 12px; color: #475569;"><strong>Due Date:</strong> {activeInvoiceForSilentExport.due}</p>
          </div>
        </div>

        <!-- Bill To -->
        <div style="margin-bottom: 25px; display: flex; justify-content: space-between;">
          <div>
            <h3 style="margin: 0 0 6px 0; font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Bill To:</h3>
            <p style="margin: 0; font-size: 15px; font-weight: 700; color: #0f172a;">{activeInvoiceForSilentExport.customer_name || (activeInvoiceForSilentExport.project.startsWith('Direct Sale: ') ? activeInvoiceForSilentExport.project.substring(13) : activeInvoiceForSilentExport.project)}</p>
            <p style="margin: 4px 0 0 0; font-size: 13px; color: #475569;"><strong>Billing Reference:</strong> {activeInvoiceForSilentExport.challan}</p>
          </div>
          <div style="text-align: right;">
            <h3 style="margin: 0 0 6px 0; font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Status:</h3>
            <span class="badge status-{activeInvoiceForSilentExport.status.toLowerCase()}" style="display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 700; border-radius: 4px;">{activeInvoiceForSilentExport.status}</span>
          </div>
        </div>

        <!-- Items Table -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 13px;">
          <thead>
            <tr style="border-bottom: 2px solid #e2e8f0; background-color: #f8fafc;">
              <th style="padding: 10px; text-align: left; color: #475569; font-weight: 700;">Description</th>
              <th style="padding: 10px; text-align: right; color: #475569; font-weight: 700; width: 80px;">Qty</th>
              <th style="padding: 10px; text-align: right; color: #475569; font-weight: 700; width: 120px;">Unit Price</th>
              <th style="padding: 10px; text-align: right; color: #475569; font-weight: 700; width: 140px;">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr style="border-bottom: 1px solid #e2e8f0;">
              <td style="padding: 12px 10px; color: #0f172a;">
                Fire Safety Equipment supply & services (Reference: {activeInvoiceForSilentExport.challan})
                <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Compliance: {activeInvoiceForSilentExport.vat_challan}</div>
              </td>
              <td style="padding: 12px 10px; text-align: right; color: #0f172a;">{formatQty(activeInvoiceForSilentExport.qty)}</td>
              <td style="padding: 12px 10px; text-align: right; color: #0f172a;">{formatMoney(activeInvoiceForSilentExport.price)}</td>
              <td style="padding: 12px 10px; text-align: right; color: #0f172a; font-weight: 700;">{formatMoney(activeInvoiceForSilentExport.qty * activeInvoiceForSilentExport.price)}</td>
            </tr>
          </tbody>
        </table>

        <!-- Totals -->
        <div style="display: flex; justify-content: flex-end; margin-bottom: 30px;">
          <div style="width: 280px; font-size: 13px;">
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9; color: #475569;">
              <span>Subtotal:</span>
              <span style="font-weight: 700; color: #0f172a;">{formatMoney(activeInvoiceForSilentExport.qty * activeInvoiceForSilentExport.price)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9; color: #475569;">
              <span>NBR VAT (15%):</span>
              <span style="font-weight: 700; color: #0f172a;">{formatMoney(activeInvoiceForSilentExport.vat_challan === 'VAT Free (Exempt)' ? 0 : (activeInvoiceForSilentExport.qty * activeInvoiceForSilentExport.price * 0.15))}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; font-size: 15px; font-weight: 800; color: #1e3a8a; border-top: 2px double #cbd5e1; margin-top: 4px;">
              <span>Total Payable:</span>
              <span>{formatMoney(activeInvoiceForSilentExport.qty * activeInvoiceForSilentExport.price * (activeInvoiceForSilentExport.vat_challan === 'VAT Free (Exempt)' ? 1.0 : 1.15))}</span>
            </div>
          </div>
        </div>

        <!-- Footer terms -->
        <div style="border-top: 1px solid #cbd5e1; padding-top: 15px; text-align: center; font-size: 11px; color: #64748b; line-height: 1.5;">
          <p style="margin: 0;">Certified under BNBC safety codes & standard fire protection guidelines.</p>
          <p style="margin: 2px 0 0 0; font-weight: 700; color: #475569;">Thank you for your business!</p>
        </div>
      </div>
    {/if}
  </main>
{/if}

<style>
  :global(body) {
    margin: 0;
    font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
    background-color: #0b0d11;
    color: #e2e8f0;
    overflow-x: hidden;
  }

  /* Header status and theme toggle layout */
  .header-status-area {
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .btn-theme-toggle-header {
    background-color: #12161f;
    border: 1px solid #232a35;
    color: #e2e8f0;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease-in-out;
  }

  .btn-theme-toggle-header:hover {
    background-color: #1e293b;
    border-color: #f97316;
  }

  /* Login footer and theme toggle styling */
  .login-footer {
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid #232a35;
    display: flex;
    justify-content: center;
  }

  .btn-theme-toggle-login {
    background-color: #171d28;
    border: 1px solid #232a35;
    color: #e2e8f0;
    padding: 8px 18px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease-in-out;
  }

  .btn-theme-toggle-login:hover {
    background-color: #1e293b;
    border-color: #f97316;
  }

  /* Tabpane flex layouts */
  .tab-pane {
    display: flex;
    flex-direction: column;
    flex-grow: 1;
  }

  /* Compliance reminder toggle styling */
  .compliance-reminder-container {
    margin-top: auto;
    margin-bottom: 24px;
    padding-top: 24px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
  }

  .compliance-reminder-container .warning-panel {
    width: 100%;
    max-width: 500px;
    text-align: left;
    box-sizing: border-box;
  }

  .btn-reminder-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background-color: rgba(245, 158, 11, 0.1);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
  }

  .btn-reminder-toggle:hover {
    background-color: rgba(245, 158, 11, 0.2);
    border-color: #fbbf24;
  }

  .btn-reminder-toggle.active {
    background-color: #fbbf24;
    color: #1e1b4b;
    border-color: #fbbf24;
  }

  :global(body.light-mode) .btn-reminder-toggle {
    background-color: #fef3c7;
    color: #b45309;
    border-color: #fde68a;
  }
  :global(body.light-mode) .btn-reminder-toggle:hover {
    background-color: #fde68a;
  }
  :global(body.light-mode) .btn-reminder-toggle.active {
    background-color: #d97706;
    color: #ffffff;
    border-color: #d97706;
  }

  /* Light Mode styles override */
  :global(body.light-mode) {
    background-color: #f8fafc;
    color: #334155;
  }

  :global(body.light-mode) .login-wrapper {
    background-color: #f8fafc !important;
  }

  :global(body.light-mode) .btn-theme-toggle-header {
    background-color: #ffffff;
    border-color: #cbd5e1;
    color: #334155;
  }

  :global(body.light-mode) .btn-theme-toggle-header:hover {
    background-color: #f1f5f9;
    border-color: #f97316;
  }

  :global(body.light-mode) .login-footer {
    border-top-color: #cbd5e1;
  }

  :global(body.light-mode) .btn-theme-toggle-login {
    background-color: #f1f5f9;
    border-color: #cbd5e1;
    color: #334155;
  }

  :global(body.light-mode) .btn-theme-toggle-login:hover {
    background-color: #e2e8f0;
    border-color: #f97316;
  }

  :global(body.light-mode) .card,
  :global(body.light-mode) .bg-solid-gray,
  :global(body.light-mode) .login-card,
  :global(body.light-mode) .sidebar,
  :global(body.light-mode) .tax-estimation,
  :global(body.light-mode) .metric-card {
    background: #ffffff !important;
    background-color: #ffffff !important;
    border-color: #cbd5e1 !important;
    color: #334155 !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03) !important;
  }

  :global(body.light-mode) .card:hover,
  :global(body.light-mode) .metric-card:hover {
    border-color: rgba(249, 115, 22, 0.5) !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04) !important;
  }

  :global(body.light-mode) .bg-solid-red {
    background: #fef2f2 !important;
    background-color: #fef2f2 !important;
    border-color: #fca5a5 !important;
    color: #991b1b !important;
  }

  :global(body.light-mode) .bg-solid-red h3,
  :global(body.light-mode) .bg-solid-red .value,
  :global(body.light-mode) .bg-solid-red span {
    color: #991b1b !important;
  }

  :global(body.light-mode) .card h2,
  :global(body.light-mode) .metric-card .value,
  :global(body.light-mode) .content-header h1,
  :global(body.light-mode) .text-bold,
  :global(body.light-mode) .quote-header h3,
  :global(body.light-mode) .section-title,
  :global(body.light-mode) .calc-row strong {
    color: #0f172a !important;
  }

  :global(body.light-mode) .login-header h2,
  :global(body.light-mode) .logo-text h2,
  :global(body.light-mode) .grand-total-amount {
    color: #c2410c !important;
  }

  :global(body.light-mode) .guide-box,
  :global(body.light-mode) .login-hints,
  :global(body.light-mode) .node-details,
  :global(body.light-mode) .challan-card,
  :global(body.light-mode) .calculation-summary-box {
    background-color: #fff7ed !important;
    border-color: #ffedd5 !important;
  }

  :global(body.light-mode) .guide-box p,
  :global(body.light-mode) .metric-card h3,
  :global(body.light-mode) .metric-card span,
  :global(body.light-mode) .login-header p,
  :global(body.light-mode) .nav-links button,
  :global(body.light-mode) .header-status,
  :global(body.light-mode) th,
  :global(body.light-mode) .standard-form label,
  :global(body.light-mode) .pill-id,
  :global(body.light-mode) .calculation-summary-box h3,
  :global(body.light-mode) .project-name,
  :global(body.light-mode) .notes,
  :global(body.light-mode) .logo-text span,
  :global(body.light-mode) .btn-close-modal {
    color: #64748b !important;
  }

  :global(body.light-mode) td {
    border-color: #cbd5e1;
    color: #334155;
  }

  :global(body.light-mode) th {
    border-color: #cbd5e1;
  }

  :global(body.light-mode) .code-sku {
    background-color: #fef3c7 !important;
    border-color: #fde68a !important;
    color: #b45309 !important;
  }

  :global(body.light-mode) .standard-form input,
  :global(body.light-mode) .standard-form select,
  :global(body.light-mode) .standard-form textarea {
    background-color: #ffffff;
    border-color: #cbd5e1;
    color: #0f172a;
  }

  :global(body.light-mode) .nav-links button:hover,
  :global(body.light-mode) .nav-links button.active {
    background-color: #f1f5f9 !important;
    color: #0f172a !important;
  }

  :global(body.light-mode) .btn-cancel {
    background-color: #e2e8f0;
    border-color: #cbd5e1;
    color: #334155;
  }
  :global(body.light-mode) .btn-cancel:hover {
    background-color: #cbd5e1;
  }
  :global(body.light-mode) .btn-remind-sent {
    background-color: #d1fae5 !important;
    color: #065f46 !important;
    border: 1px solid #a7f3d0 !important;
  }
  :global(body.light-mode) .btn-remind-sent:hover {
    background-color: #a7f3d0 !important;
  }

  :global(body.light-mode) .bg-dark-disabled {
    background-color: #f1f5f9 !important;
    border-color: #cbd5e1 !important;
    color: #94a3b8 !important;
  }

  :global(body.light-mode) .checkbox-label {
    color: #334155 !important;
  }

  :global(body.light-mode) .negotiation-tree::before,
  :global(body.light-mode) .tree-node::before {
    background-color: #cbd5e1;
  }
  :global(body.light-mode) .tree-node::before {
    border-color: #ffffff;
  }
  :global(body.light-mode) .tree-node.approved::before {
    background-color: #10b981;
  }

  :global(body.light-mode) .highlight {
    background-color: #f8fafc;
    border-color: #cbd5e1;
  }

  :global(body.light-mode) .warning-panel {
    background-color: #fef2f2 !important;
    border-color: #fca5a5 !important;
  }
  :global(body.light-mode) .warning-panel h3 {
    color: #b91c1c !important;
  }
  :global(body.light-mode) .warning-panel p {
    color: #7f1d1d !important;
  }
  :global(body.light-mode) .warning-panel ul {
    color: #991b1b !important;
  }

  :global(body.light-mode) .node-ver {
    color: #c2410c !important;
    background-color: #ffedd5 !important;
  }

  :global(body.light-mode) .bucket-bar .amount {
    color: #0f172a !important;
  }

  :global(body.light-mode) .bar-fill,
  :global(body.light-mode) .progress-bar {
    background-color: #e2e8f0 !important;
  }
  :global(body.light-mode) .bar-fill.blue {
    background-color: #2563eb !important;
  }

  /* Emerald/green badges for type-grn, success, approved status */
  :global(body.light-mode) .badge.success,
  :global(body.light-mode) .badge.type-grn,
  :global(body.light-mode) .badge.status-approved {
    background-color: #d1fae5 !important;
    color: #065f46 !important;
    border-color: #a7f3d0 !important;
  }

  /* Red/danger badges for danger, rejected status */
  :global(body.light-mode) .badge.danger,
  :global(body.light-mode) .badge.status-rejected {
    background-color: #fee2e2 !important;
    color: #991b1b !important;
    border-color: #fca5a5 !important;
  }

  /* Blue/info badges for categories, dispatch type, draft status */
  :global(body.light-mode) .badge.category,
  :global(body.light-mode) .badge.type-dispatch,
  :global(body.light-mode) .badge.status-draft {
    background-color: #dbeafe !important;
    color: #1e40af !important;
    border-color: #bfdbfe !important;
  }

  /* Amber/yellow badges for transfer type */
  :global(body.light-mode) .badge.type-transfer {
    background-color: #fef3c7 !important;
    color: #92400e !important;
    border-color: #fde68a !important;
  }

  /* Gray badges for superseded status */
  :global(body.light-mode) .badge.status-superseded {
    background-color: #f3f4f6 !important;
    color: #374151 !important;
    border-color: #e5e7eb !important;
  }

  /* Color helpers */
  :global(body.light-mode) .color-blue {
    color: #1d4ed8 !important;
  }
  :global(body.light-mode) .color-green {
    color: #047857 !important;
  }

  /* Operation buttons (Modify / Remove) overrides in Light Mode */
  :global(body.light-mode) .btn-op.edit {
    background-color: #eff6ff !important;
    border: 1px solid #bfdbfe !important;
    color: #1d4ed8 !important;
  }
  :global(body.light-mode) .btn-op.edit:hover {
    background-color: #1d4ed8 !important;
    color: #ffffff !important;
    border-color: #1d4ed8 !important;
  }

  :global(body.light-mode) .btn-op.delete {
    background-color: #fef2f2 !important;
    border: 1px solid #fca5a5 !important;
    color: #b91c1c !important;
  }
  :global(body.light-mode) .btn-op.delete:hover {
    background-color: #b91c1c !important;
    color: #ffffff !important;
    border-color: #b91c1c !important;
  }

  /* Code/Serial badges (.pill-id) overrides in Light Mode */
  :global(body.light-mode) .pill-id {
    background-color: #f1f5f9 !important;
    border-color: #cbd5e1 !important;
    color: #475569 !important;
  }

  /* Solid card container style */
  .card {
    background: linear-gradient(135deg, #12161f 0%, #0d1017 100%);
    border: 1px solid #232a35;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -4px rgba(0, 0, 0, 0.3);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  }
  .card:hover {
    border-color: rgba(249, 115, 22, 0.4);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 10px 10px -5px rgba(0, 0, 0, 0.4);
  }

  /* Guide card styling for user onboarding */
  .guide-box {
    background-color: rgba(249, 115, 22, 0.04);
    border: 1px solid rgba(249, 115, 22, 0.2);
    padding: 18px;
    border-radius: 8px;
    margin-bottom: 24px;
  }

  .guide-box h4 {
    margin: 0 0 6px 0;
    color: #f97316;
    font-size: 15px;
    font-weight: 700;
  }

  .guide-box p {
    margin: 0;
    font-size: 13px;
    color: #94a3b8;
    line-height: 1.5;
  }

  /* Metrics solid styling */
  .metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
  }

  .metric-card {
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #232a35;
    color: #fff;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  }
  .metric-card:hover {
    transform: translateY(-2px);
    border-color: rgba(249, 115, 22, 0.5);
    box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.4);
  }

  .bg-solid-gray { background: linear-gradient(135deg, #12161f 0%, #0d1017 100%); }
  .bg-solid-red { background: linear-gradient(135deg, #2a0808 0%, #1c0505 100%); border-color: rgba(239, 68, 68, 0.4); }
  .border-orange { border-color: rgba(249, 115, 22, 0.4); }

  .metric-card h3 {
    margin: 0 0 10px 0;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #94a3b8;
    font-weight: 700;
  }

  .metric-card .value {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 6px 0;
    color: #f8fafc;
  }

  .metric-card span {
    font-size: 12px;
    color: #94a3b8;
  }

  /* Login Overlay Styling */
  .login-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background-color: #0b0d11;
  }

  .login-card {
    background-color: #12161f;
    border: 1px solid #232a35;
    border-radius: 12px;
    padding: 40px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
  }

  .login-header {
    text-align: center;
    margin-bottom: 30px;
  }

  .flame-logo {
    margin-bottom: 12px;
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .app-logo-img {
    height: 64px;
    width: auto;
    object-fit: contain;
  }

  .login-header h2 {
    margin: 0;
    font-size: 26px;
    font-weight: 800;
    color: #f97316;
    letter-spacing: 0.5px;
  }

  .login-header p {
    margin: 4px 0 0 0;
    font-size: 12px;
    color: #94a3b8;
    text-transform: uppercase;
    font-weight: 600;
  }



  /* Operations Buttons Cell in Product Directory */
  .operations-cell {
    display: flex;
    gap: 8px;
  }

  .btn-op {
    border: none;
    border-radius: 4px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s, transform 0.1s ease;
  }

  .btn-op:active {
    transform: scale(0.96);
  }

  .btn-op.edit {
    background-color: #1e293b;
    color: #3b82f6;
    border: 1px solid #3b82f6;
  }

  .btn-op.edit:hover { background-color: #3b82f6; color: #fff; }

  .btn-op.delete {
    background-color: #1e293b;
    color: #ef4444;
    border: 1px solid #ef4444;
  }

  .btn-op.delete:hover { background-color: #ef4444; color: #fff; }

  .btn-cancel {
    background-color: #232a35;
    color: #cbd5e1;
    margin-left: 8px;
    border: 1px solid #334155;
  }

  .btn-cancel:hover { background-color: #334155; }
  .btn-remind-sent {
    background-color: #10b981;
    color: #ffffff;
    border: 1px solid #10b981;
  }
  .btn-remind-sent:hover {
    background-color: #059669;
    color: #ffffff;
  }

  .form-actions {
    display: flex;
    align-items: center;
    margin-top: 10px;
  }

  /* Sidebar footers & logout */
  .sidebar-footer {
    margin-top: auto;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .btn-logout {
    border: 1px solid #ef4444;
    background: none;
    color: #ef4444;
    border-radius: 6px;
    padding: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s;
    font-size: 13px;
  }

  .btn-logout:hover {
    background-color: rgba(239, 68, 68, 0.1);
  }

  .erp-container {
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr);
    min-height: 100vh;
  }

  /* Sidebar styling */
  .sidebar {
    background-color: #12161f;
    border-right: 1px solid #232a35;
    padding: 24px;
    display: flex;
    flex-direction: column;
  }

  .logo-link {
    cursor: pointer;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    transition: transform 0.2s ease;
    text-decoration: none;
  }
  .logo-link:hover {
    transform: scale(1.05);
  }

  .logo-btn {
    background: none;
    border: none;
    padding: 0;
    margin: 0;
    width: 100%;
    text-align: left;
    cursor: pointer;
    display: block;
    transition: transform 0.2s ease;
  }
  .logo-btn:hover {
    transform: scale(1.02);
  }
  .logo-btn:focus {
    outline: none;
  }

  .logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 40px;
  }

  .flame-icon {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .sidebar-logo-img {
    height: 32px;
    width: auto;
    object-fit: contain;
  }

  .logo-text h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
    color: #f97316;
    letter-spacing: 0.5px;
  }

  .logo-text span {
    font-size: 11px;
    color: #94a3b8;
    text-transform: uppercase;
    font-weight: 600;
  }

  .nav-links {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex-grow: 1;
  }

  .nav-links button {
    background: none;
    border: none;
    color: #94a3b8;
    text-align: left;
    padding: 14px 16px;
    font-size: 14px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s ease-in-out, transform 0.1s ease;
  }

  .nav-links button:active {
    transform: scale(0.98);
  }

  .nav-links button:hover, .nav-links button.active {
    background-color: #232a35;
    color: #f8fafc;
    box-shadow: inset 4px 0 0 #f97316;
  }

  /* Main content styling */
  .main-content {
    padding: 40px;
    overflow-y: auto;
    min-width: 0;
    box-sizing: border-box;
  }

  .content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    border-bottom: 1px solid #232a35;
    padding-bottom: 20px;
  }

  .content-header h1 {
    font-size: 30px;
    font-weight: 800;
    color: #f8fafc;
    margin: 0;
  }

  .header-status {
    font-size: 13px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .pulse-green {
    width: 8px;
    height: 8px;
    background-color: #10b981;
    border-radius: 50%;
    display: inline-block;
  }

  .dashboard-body {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    align-items: start;
  }

  .card-wide {
    flex: 2.2 1 650px;
    min-width: 0;
  }
  .card-narrow {
    flex: 1 1 350px;
    min-width: 0;
  }
  .card h2 {
    font-size: 18px;
    font-weight: 700;
    color: #f8fafc;
    margin-top: 0;
    margin-bottom: 16px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  /* Tables styling */
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }

  th {
    text-align: left;
    color: #94a3b8;
    font-weight: 700;
    padding: 12px;
    border-bottom: 2px solid #232a35;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.5px;
  }

  td {
    padding: 12px;
    border-bottom: 1px solid #232a35;
    color: #cbd5e1;
  }

  tr {
    transition: background-color 0.15s ease;
  }

  tr:hover td {
    background-color: rgba(249, 115, 22, 0.02) !important;
  }

  .code-sku {
    font-family: 'JetBrains Mono', monospace;
    background-color: #171d28;
    border: 1px solid #232a35;
    color: #f97316;
    padding: 2px 4px;
    border-radius: 3px;
  }

  .text-bold { font-weight: 700; color: #f8fafc; }

  /* Badges */
  .badge {
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    display: inline-block;
  }

  .badge.category { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
  .badge.success { background-color: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
  .badge.danger { background-color: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

  /* AR Aging buckets custom visual bar chart */
  .aging-buckets {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .bucket-bar {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 13px;
  }

  .bucket-bar .amount {
    text-align: right;
    font-weight: 700;
    color: #f8fafc;
  }

  .bar-fill {
    height: 6px;
    background-color: #232a35;
    border-radius: 3px;
  }

  .bar-fill.blue {
    background-color: #3b82f6;
  }

  /* Warning panels */
  .warning-panel {
    border: 1px solid #ef4444;
    background-color: #1c1616;
  }

  .warning-panel h3 {
    margin-top: 0;
    color: #f87171;
    font-size: 14px;
    font-weight: 700;
  }

  .warning-panel p {
    font-size: 13px;
    color: #cbd5e1;
    margin: 4px 0 10px 0;
  }

  .warning-panel ul {
    margin: 0;
    padding-left: 20px;
    font-size: 13px;
    line-height: 1.6;
    color: #fca5a5;
  }

  /* Form layouts */
  .standard-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  /* Custom Switch Toggle Styles */
  .switch-container {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    user-select: none;
    padding: 6px 0;
  }

  .switch-label-text {
    font-size: 12px;
    color: #94a3b8;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 22px;
  }

  .switch input {
    opacity: 0;
    width: 0;
    height: 0;
  }

  .slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #232a35;
    transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 22px;
    border: 1px solid #334155;
  }

  .slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: #94a3b8;
    transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 50%;
  }

  input:checked + .slider {
    background-color: rgba(249, 115, 22, 0.15);
    border-color: #f97316;
  }

  input:checked + .slider:before {
    transform: translateX(22px);
    background-color: #f97316;
  }

  :global(body.light-mode) .slider {
    background-color: #e2e8f0;
    border-color: #cbd5e1;
  }
  :global(body.light-mode) .slider:before {
    background-color: #64748b;
  }
  :global(body.light-mode) input:checked + .slider {
    background-color: rgba(249, 115, 22, 0.1);
    border-color: #f97316;
  }
  :global(body.light-mode) input:checked + .slider:before {
    background-color: #f97316;
  }

  .standard-form label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 12px;
    color: #94a3b8;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .standard-form input, .standard-form select, .standard-form textarea {
    background-color: #171d28;
    border: 1px solid #232a35;
    border-radius: 4px;
    padding: 10px;
    color: #f8fafc;
    font-size: 13px;
    font-family: inherit;
  }

  .standard-form input:focus, .standard-form select:focus, .standard-form textarea:focus {
    outline: none;
    border-color: #f97316;
  }

  .highlight {
    background-color: #171d28;
    border: 1px dashed #232a35;
    padding: 12px;
    border-radius: 4px;
  }

  .btn {
    border: none;
    border-radius: 4px;
    padding: 12px;
    font-weight: 700;
    cursor: pointer;
    font-size: 13px;
    transition: background-color 0.2s, transform 0.1s ease;
  }

  .btn:active {
    transform: scale(0.98);
  }

  .btn-primary {
    background-color: #f97316;
    color: #0b0d11;
    font-weight: 700;
  }

  .btn-primary:hover {
    background-color: #ea580c;
    color: #0b0d11;
  }

  /* Alerts */
  .alert {
    padding: 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 16px;
  }

  .alert.error { background-color: #2a1b1b; color: #f87171; border: 1px solid #ef4444; }
  .alert.success { background-color: #1b2a21; color: #34d399; border: 1px solid #10b981; }

  /* Double-entry pills */
  .badge.type-grn { background-color: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
  .badge.type-dispatch { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
  .badge.type-transfer { background-color: rgba(245, 158, 11, 0.1); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }

  .pill-id {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    background-color: #171d28;
    border: 1px solid #232a35;
    padding: 2px 4px;
    border-radius: 3px;
    color: #94a3b8;
  }

  /* Sales negotiation tree styling */
  .quote-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #232a35;
    padding-bottom: 12px;
    margin-bottom: 16px;
  }

  .quote-header h3 { margin: 0; font-size: 14px; color: #f8fafc; }
  .project-name { font-size: 12px; color: #94a3b8; }

  .negotiation-tree {
    display: flex;
    flex-direction: column;
    gap: 16px;
    position: relative;
    padding-left: 20px;
  }

  .negotiation-tree::before {
    content: '';
    position: absolute;
    left: 7px;
    top: 10px;
    bottom: 10px;
    width: 2px;
    background-color: #232a35;
  }

  .tree-node {
    display: flex;
    gap: 16px;
    position: relative;
  }

  .tree-node::before {
    content: '';
    position: absolute;
    left: -17px;
    top: 10px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #64748b;
    border: 2px solid #12161f;
  }

  .tree-node.approved::before { background-color: #10b981; }

  .node-ver {
    font-size: 11px;
    font-weight: 800;
    color: #f97316;
    background-color: rgba(249, 115, 22, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
    height: fit-content;
  }

  .node-details {
    flex-grow: 1;
    background-color: #171d28;
    border-radius: 4px;
    padding: 12px;
    border: 1px solid #232a35;
  }

  .node-meta {
    display: flex;
    gap: 16px;
    font-size: 13px;
    align-items: center;
    flex-wrap: wrap;
  }

  .badge.status-approved { background-color: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
  .badge.status-rejected { background-color: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
  .badge.status-superseded { background-color: rgba(100, 116, 139, 0.1); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.3); }
  .badge.status-draft { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }

  .notes {
    margin: 8px 0 0 0;
    font-size: 12px;
    color: #94a3b8;
    font-style: italic;
  }

  /* Challan progress boxes */
  .challan-summary-box {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
  }

  .challan-card {
    background-color: #171d28;
    border: 1px solid #232a35;
    border-radius: 6px;
    padding: 16px;
  }



  .pill-vat {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    background-color: rgba(16, 185, 129, 0.05);
    border: 1px solid rgba(16, 185, 129, 0.2);
    color: #34d399;
    padding: 2px 4px;
    border-radius: 3px;
    font-weight: 700;
  }

  .tax-estimation {
    background-color: #12161f;
    border: 1px solid #232a35;
    padding: 12px;
    border-radius: 4px;
    font-size: 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .tax-estimation strong { font-size: 13px; color: #f97316; margin-top: 4px; }
  .section-title { font-size: 14px; margin-top: 30px; margin-bottom: 12px; color: #f8fafc; text-transform: uppercase; }

  /* Simple Invoice Generator Specific Styles */
  .form-row-2 {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 16px;
  }

  .form-row-3 {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 16px;
  }

  .bg-dark-disabled {
    background-color: #0b0d11 !important;
    border-color: #171d28 !important;
    color: #64748b !important;
  }

  .checkbox-label {
    display: flex;
    flex-direction: row !important;
    align-items: center;
    gap: 8px !important;
    cursor: pointer;
    font-size: 13px !important;
    text-transform: none !important;
    color: #cbd5e1 !important;
  }

  .checkbox-label input {
    cursor: pointer;
    margin: 0;
  }

  .calculation-summary-box {
    background-color: #171d28;
    border: 1px solid #232a35;
    padding: 16px;
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .calculation-summary-box h3 {
    margin: 0 0 6px 0;
    font-size: 12px;
    text-transform: uppercase;
    color: #94a3b8;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #232a35;
    padding-bottom: 6px;
  }

  .calc-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
  }

  .calc-row strong {
    color: #cbd5e1;
  }

  .total-row {
    border-top: 1px dashed #334155;
    padding-top: 10px;
    font-size: 14px;
    font-weight: 700;
  }

  .grand-total-amount {
    color: #f97316;
    font-size: 16px;
    font-weight: 800;
  }

  .color-disabled {
    color: #475569 !important;
  }

  /* Modal backdrop and card styles */
  .modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(11, 13, 17, 0.85);
    backdrop-filter: blur(8px);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }

  .modal-card.user-guide-modal {
    background-color: #12161f;
    border: 1px solid #232a35;
    border-radius: 16px;
    width: 100%;
    max-width: 750px;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 80px);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
    overflow: hidden;
  }

  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #232a35;
  }

  .modal-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    color: #f97316;
    letter-spacing: 0.5px;
  }

  .btn-close-modal {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 20px;
    cursor: pointer;
    transition: color 0.2s;
    padding: 4px 8px;
    border-radius: 4px;
  }

  .btn-close-modal:hover {
    color: #ef4444;
  }

  .scrollable-guide-content {
    padding: 24px;
    overflow-y: auto;
    line-height: 1.6;
    color: #cbd5e1;
  }

  .scrollable-guide-content h3 {
    font-size: 16px;
    font-weight: 700;
    color: #f8fafc;
    margin-top: 24px;
    margin-bottom: 10px;
    border-bottom: 1px solid #232a35;
    padding-bottom: 6px;
  }

  .scrollable-guide-content h3:first-of-type {
    margin-top: 0;
  }

  .scrollable-guide-content p {
    margin: 0 0 16px 0;
    font-size: 13px;
    color: #cbd5e1;
  }

  .scrollable-guide-content ul {
    margin: 0 0 16px 0;
    padding-left: 20px;
    font-size: 13px;
    color: #cbd5e1;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .modal-alert-box {
    background-color: rgba(249, 115, 22, 0.05);
    border: 1px solid rgba(249, 115, 22, 0.2);
    padding: 14px 18px;
    border-radius: 8px;
    color: #fbbf24;
    font-size: 13px;
    margin: 16px 0;
    line-height: 1.5;
  }

  .modal-alert-box strong {
    color: #fbbf24;
  }

  /* Light mode overrides for modal */
  :global(body.light-mode) .modal-backdrop {
    background-color: rgba(248, 250, 252, 0.85);
  }

  :global(body.light-mode) .modal-card.user-guide-modal {
    background-color: #ffffff;
    border-color: #cbd5e1;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
  }

  :global(body.light-mode) .modal-header {
    border-bottom-color: #cbd5e1;
  }

  :global(body.light-mode) .scrollable-guide-content {
    color: #334155;
  }

  :global(body.light-mode) .scrollable-guide-content h3 {
    color: #0f172a;
    border-bottom-color: #cbd5e1;
  }

  :global(body.light-mode) .scrollable-guide-content p,
  :global(body.light-mode) .scrollable-guide-content ul {
    color: #334155;
  }

  :global(body.light-mode) .modal-alert-box {
    background-color: #fffbeb;
    border-color: #fde68a;
    color: #b45309;
  }

  :global(body.light-mode) .modal-alert-box strong {
    color: #b45309;
  }

  /* -------------------------------------------------------------
   * Mobile-First ERP Responsive Stylesheet
   * ------------------------------------------------------------- */
  .responsive-table-container {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border: 1px solid #232a35;
    border-radius: 6px;
    margin-bottom: 15px;
  }
  :global(body.light-mode) .responsive-table-container {
    border-color: #cbd5e1;
  }

  .btn-mobile-menu-toggle {
    display: none;
  }

  .header-title-row {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  @media (max-width: 1100px) {
    .erp-container {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      overflow-x: hidden;
    }

    .mobile-sidebar-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(15, 23, 42, 0.7);
      backdrop-filter: blur(4px);
      z-index: 999;
      animation: fadeIn 0.2s ease-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: 280px;
      z-index: 1000;
      transform: translateX(-100%);
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
      background: rgba(18, 22, 31, 0.75) !important;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-right: 1px solid rgba(255, 255, 255, 0.08) !important;
    }

    :global(body.light-mode) .sidebar {
      background: rgba(255, 255, 255, 0.75) !important;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-right: 1px solid rgba(15, 23, 42, 0.08) !important;
    }

    .sidebar.open {
      transform: translateX(0);
    }

    .main-content {
      padding: 20px 16px;
      width: 100%;
      box-sizing: border-box;
      overflow-x: hidden;
    }

    .content-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 16px;
      margin-bottom: 20px;
      padding-bottom: 16px;
    }

    .btn-mobile-menu-toggle {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 44px;
      height: 44px;
      background: rgba(30, 41, 59, 0.45);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
      border-radius: 6px;
      color: #f8fafc;
      font-size: 22px;
      cursor: pointer;
      transition: background-color 0.2s, border-color 0.2s;
    }
    .btn-mobile-menu-toggle:hover {
      background: rgba(30, 41, 59, 0.6);
      border-color: rgba(255, 255, 255, 0.15);
    }
    :global(body.light-mode) .btn-mobile-menu-toggle {
      background: rgba(255, 255, 255, 0.6) !important;
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border: 1px solid rgba(15, 23, 42, 0.08) !important;
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.05);
      color: #1e3a8a;
    }
    :global(body.light-mode) .btn-mobile-menu-toggle:hover {
      background: rgba(255, 255, 255, 0.85) !important;
      border-color: rgba(15, 23, 42, 0.15) !important;
    }

    .content-header h1 {
      font-size: 20px;
      line-height: 1.3;
    }

    .header-status-area {
      width: 100%;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
    }

    .metrics-grid {
      grid-template-columns: 1fr !important;
      gap: 16px;
    }

    .dashboard-body {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .card, .card-wide, .card-narrow {
      width: 100% !important;
      max-width: 100% !important;
      min-width: 0 !important;
      box-sizing: border-box !important;
      padding: 16px !important;
      border-radius: 8px !important;
      margin-bottom: 16px !important;
    }

    /* Form and touch target scaling */
    .standard-form {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .standard-form label {
      font-size: 14px;
    }

    .guide-box {
      padding: 12px !important;
      margin-bottom: 16px !important;
    }

    .responsive-table-container table {
      min-width: 650px !important;
      width: 100%;
    }

    .form-row-2, .form-row-3 {
      grid-template-columns: 1fr !important;
      gap: 12px !important;
    }

    .login-card {
      padding: 24px 16px !important;
      max-width: 92% !important;
    }

    .standard-form input,
    .standard-form select,
    .standard-form textarea,
    .standard-form button,
    .btn-op {
      min-height: 44px !important;
      font-size: 16px !important; /* Prevents viewport scaling/zooming on mobile focus */
      padding: 10px 14px !important;
      box-sizing: border-box;
    }

    .form-actions {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .form-actions button {
      width: 100% !important;
    }

    .tree-node {
      flex-direction: column;
      align-items: flex-start;
      gap: 12px;
    }

    .node-meta {
      flex-direction: column;
      align-items: flex-start;
      gap: 8px;
    }

    .modal-card {
      width: 95% !important;
      max-height: 90vh !important;
      margin: 10px auto;
      padding: 16px !important;
    }
  }
  @media print {
    :global(body), :global(html) {
      background-color: #ffffff !important;
      color: #000000 !important;
    }
    .sidebar, .content-header, .no-print, .modal-backdrop, .modal-header, .modal-footer, .guide-box, .btn-mobile-menu-toggle {
      display: none !important;
      visibility: hidden !important;
    }
    main, .erp-container, .main-content, .tab-pane, .dashboard-body {
      display: block !important;
      padding: 0 !important;
      margin: 0 !important;
      width: 100% !important;
      background: none !important;
    }
    .modal-card {
      position: absolute !important;
      left: 0 !important;
      top: 0 !important;
      width: 100% !important;
      max-width: 100% !important;
      box-shadow: none !important;
      border: none !important;
      background: #ffffff !important;
      padding: 0 !important;
      margin: 0 !important;
    }
    .invoice-printable-area {
      display: block !important;
      width: 100% !important;
      margin: 0 !important;
      padding: 0 !important;
      background: #ffffff !important;
      color: #000000 !important;
    }
  }
</style>

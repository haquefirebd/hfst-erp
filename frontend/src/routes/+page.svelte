<script lang="ts">
  import { onMount } from 'svelte';
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

  // -------------------------------------------------------------
  // Data Model States (Runes Mode)
  // -------------------------------------------------------------
  let projects = $state([
    { id: 'proj-1', name: 'High-Rise Tower Fire Sprinkler installation', client: 'DHAKA METROPOLIS DEVELOPERS LTD', location: 'Uttara, Dhaka' },
    { id: 'proj-2', name: 'Bashundhara City mall Alarm Retrofit', client: 'BASHUNDHARA GROUP', location: 'Baridhara, Dhaka' }
  ]);

  let items = $state([
    { id: 'item-1', sku: 'FM200-CYL-120L', name: 'FM200 Clean Agent Cylinder 120L', type: 'BatchManaged', average_consumption_rate: 1.5, lead_time_days: 45, reorder_point: 67.5, unit: 'Pcs', initial_quantity: 0, selling_price: 125000, purchase_price: 95000 },
    { id: 'item-2', sku: 'PANEL-NFX-72', name: 'Addressable Fire Alarm Control Panel', type: 'Serialized', average_consumption_rate: 0.2, lead_time_days: 60, reorder_point: 12.0, unit: 'Pcs', initial_quantity: 0, selling_price: 350000, purchase_price: 280000 },
    { id: 'item-3', sku: 'SPRINK-HD-12', name: 'HD Brass Fire Sprinkler Head 1/2"', type: 'Standard', average_consumption_rate: 10.0, lead_time_days: 15, reorder_point: 150.0, unit: 'Pcs', initial_quantity: 0, selling_price: 750, purchase_price: 450 }
  ]);

  let warehouses = $state([
    { id: 'wh-1', name: 'Uttara Central Depot', location: 'Uttara, Dhaka', active: true },
    { id: 'wh-2', name: 'Chittagong Port Transit Warehouse', location: 'Halishahar, Chittagong', active: true }
  ]);

  // Stock Movement History Log
  let stockLedger = $state([
    { id: 'l-1', item_sku: 'FM200-CYL-120L', from: 'Supplier (Stock Entry)', to: 'Uttara Central Depot', qty: 100, type: 'Stock In', date: '2026-06-24' },
    { id: 'l-2', item_sku: 'PANEL-NFX-72', from: 'Supplier (Stock Entry)', to: 'Uttara Central Depot', qty: 15, type: 'Stock In', date: '2026-06-24' },
    { id: 'l-3', item_sku: 'PANEL-NFX-72', from: 'Uttara Central Depot', to: 'High-Rise Tower Project', qty: 1, type: 'Stock Out', serial: 'SN-NFX-9081', date: '2026-06-24' }
  ]);

  // Serialized Assets Track
  let serializedAssets = $state([
    { serial: 'SN-NFX-9081', sku: 'PANEL-NFX-72', status: 'Installed', warranty: '2027-06-24', project: 'High-Rise Tower' },
    { serial: 'SN-NFX-9082', sku: 'PANEL-NFX-72', status: 'In-Stock', warranty: '2027-06-24', project: null },
    { serial: 'SN-NFX-9083', sku: 'PANEL-NFX-72', status: 'In-Stock', warranty: '2027-06-24', project: null }
  ]);

  // Batches Track (Expiry & Hydrostatic Test compliance)
  let batches = $state([
    { batch: 'BAT-FM200-01', sku: 'FM200-CYL-120L', qty: 99, mfg: '2025-01-10', expiry: '2030-01-10', hydro_due: '2029-06-15' },
    { batch: 'BAT-FM200-02', sku: 'FM200-CYL-120L', qty: 50, mfg: '2026-02-15', expiry: '2031-02-15', hydro_due: '2026-07-10' }
  ]);

  // Quote Revision Negotiation Tree
  let quotes = $state([
    {
      id: 'Q-2026-001',
      project: 'High-Rise Tower Fire Sprinkler installation',
      versions: [
        { version: 1, status: 'Superseded', total: 4500000, vat: 675000, date: '2026-06-01', notes: 'Initial client proposal estimate' },
        { version: 2, status: 'Superseded', total: 4200000, vat: 630000, date: '2026-06-10', notes: 'Discounted price offered' },
        { version: 3, status: 'Approved', total: 4150000, vat: 622500, date: '2026-06-15', notes: 'Final agreed contract rates' }
      ]
    }
  ]);

  // Challan Delivery Tracking
  let deliveryChallans = $state([
    { id: 'CHALLAN-2026-001', project: 'High-Rise Tower Fire Sprinkler installation', status: 'Delivered', item_sku: 'FM200-CYL-120L', shipped_qty: 10, invoiced_qty: 9 }
  ]);

  // Invoices & Outstanding Payments
  let invoices = $state([
    { id: 'INV-2026-001', project: 'High-Rise Tower Fire Sprinkler installation', challan: 'CHALLAN-2026-001', qty: 6, price: 125000, vat_challan: 'NBR Mushak-6.3 #9081', status: 'Paid', date: '2026-06-16', due: '2026-07-16' },
    { id: 'INV-2026-002', project: 'High-Rise Tower Fire Sprinkler installation', challan: 'CHALLAN-2026-001', qty: 3, price: 125000, vat_challan: 'NBR Mushak-6.3 #9110', status: 'Unpaid', date: '2026-06-20', due: '2026-07-20' }
  ]);

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

  // Calculate accounts receivable aging
  let arAging = $state({ current: 431250, days30: 0, days60: 0, days90: 0, days120: 0, total: 431250 });

  // -------------------------------------------------------------
  // Actions
  // -------------------------------------------------------------
  async function fetchItems() {
    try {
      const { data, error } = await supabase.from('items').select('*').order('sku');
      if (data && data.length > 0) {
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
      if (data && data.length > 0) {
        projects = data.map((p: any) => ({
          id: p.id,
          name: p.name,
          client: p.client_name,
          location: p.location
        }));
      }
    } catch (e) {}
  }

  async function fetchWarehouses() {
    try {
      const { data } = await supabase.from('warehouses').select('*').order('name');
      if (data && data.length > 0) {
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
      if (data && data.length > 0) {
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
      if (data && data.length > 0) {
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
      if (data && data.length > 0) {
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
      if (data && data.length > 0) {
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
      if (data && data.length > 0) {
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
      if (data && data.length > 0) {
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
          simpleBillingSuccess = `✔ Invoice ${nextInvId} for ${inputSimpleBilling.customer_name} issued successfully! Total Billed: ${formatMoney(finalTotal)}`;
          await fetchInvoices();
          inputSimpleBilling.customer_name = '';
          inputSimpleBilling.qty = 1;
          inputSimpleBilling.is_vat_free = false;
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
  }

  function persistLocalData() {
    if (typeof window === 'undefined') return;
    localStorage.setItem('hfst_items', JSON.stringify(items));
    localStorage.setItem('hfst_stock_ledger', JSON.stringify(stockLedger));
    localStorage.setItem('hfst_invoices', JSON.stringify(invoices));
    localStorage.setItem('hfst_serialized_assets', JSON.stringify(serializedAssets));
    localStorage.setItem('hfst_batches', JSON.stringify(batches));
    localStorage.setItem('hfst_quotes', JSON.stringify(quotes));
  }

  onMount(async () => {
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
                        <td>
                          <span class="text-bold">{formatQty(item.stock)}</span>
                          {#if item.stock <= (item.reorder_point || (item.average_consumption_rate * item.lead_time_days))}
                            <span class="badge danger" style="margin-left: 8px;">Low Stock</span>
                          {:else}
                            <span class="badge success" style="margin-left: 8px;">Safe</span>
                          {/if}
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
    </section>

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
  :global(body.light-mode) .challan-header h4,
  :global(body.light-mode) .section-title,
  :global(body.light-mode) .calc-row strong,
  :global(body.light-mode) .stat strong {
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
  :global(body.light-mode) .stat span,
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

  .login-hints {
    font-size: 12px;
    color: #94a3b8;
    background-color: #171d28;
    border: 1px solid #232a35;
    padding: 10px;
    border-radius: 6px;
    line-height: 1.4;
  }

  .login-hints code {
    font-family: 'JetBrains Mono', monospace;
    color: #f97316;
    background: #0b0d11;
    padding: 2px 4px;
    border-radius: 3px;
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
    transition: background-color 0.2s;
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
    grid-template-columns: 260px 1fr;
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
    transition: all 0.2s ease-in-out;
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
    display: grid;
    grid-template-columns: 2.2fr 1fr;
    gap: 24px;
    align-items: start;
  }

  .card-wide { grid-column: span 1; }
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
    transition: background-color 0.2s;
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

  .challan-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
  }

  .challan-header h4 { margin: 0; font-size: 14px; color: #f8fafc; }
  .challan-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
  }

  .stat { display: flex; flex-direction: column; gap: 4px; }
  .stat span { font-size: 10px; color: #94a3b8; text-transform: uppercase; font-weight: 700; }
  .stat strong { font-size: 12px; color: #cbd5e1; }

  .color-blue { color: #60a5fa !important; }
  .color-green { color: #34d399 !important; }

  .progress-container {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 11px;
    color: #94a3b8;
  }

  .progress-bar {
    height: 6px;
    background-color: #0b0d11;
    border-radius: 3px;
    overflow: hidden;
  }

  .progress-bar .fill {
    height: 100%;
    background-color: #10b981;
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

  @media (max-width: 768px) {
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
      background-color: #232a35;
      border: 1px solid #334155;
      border-radius: 6px;
      color: #f8fafc;
      font-size: 22px;
      cursor: pointer;
    }
    :global(body.light-mode) .btn-mobile-menu-toggle {
      background-color: #eff6ff;
      border-color: #bfdbfe;
      color: #1e3a8a;
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

    .card-wide, .card-narrow {
      width: 100% !important;
      max-width: 100% !important;
      margin-bottom: 0 !important;
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

    .card {
      padding: 16px !important;
      border-radius: 8px !important;
      margin-bottom: 16px !important;
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
</style>

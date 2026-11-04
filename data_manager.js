/**
 * Elamar Data Manager for Vercel & Client-Side Dashboards
 * Synchronizes Seed Data, LocalStorage, and Remote Backend
 */
(function(window) {
  'use strict';

  const STORAGE_KEY_CLIENTS = 'elamar_submitted_clients';
  const STORAGE_KEY_USER = 'elamar_portal_user';
  const STORAGE_KEY_LANG = 'elamar_lang';

  const ElamarData = {
    getLanguage: function() {
      return localStorage.getItem(STORAGE_KEY_LANG) || 'ar';
    },

    setLanguage: function(lang) {
      localStorage.setItem(STORAGE_KEY_LANG, lang);
    },

    getUsers: function() {
      const seedUsers = (window.ELAMAR_SEED_DATA && window.ELAMAR_SEED_DATA.SEED_USERS) ? window.ELAMAR_SEED_DATA.SEED_USERS : [
        { id: 1, name: "المدير التنفيذي (Admin)", email: "admin@elamar.com", role: "Admin", active: 1, referral_code: "admin" },
        { id: 13, name: "hana mohamed", email: "hanamohaed@gmail.com", role: "Sales", active: 1, referral_code: "hanamohamed-b93d83" },
        { id: 14, name: "emy mousa", email: "emymousa@gmail.com", role: "Manager", active: 1, referral_code: "emymousa-010bcd" },
        { id: 16, name: "ahmed khedr", email: "ahmedkhedr@gmail.com", role: "Manager", active: 1, referral_code: "ahmedkhedr-a3f78e" },
        { id: 17, name: "nadeen mahmoud", email: "nadeenmahmoud@gmail.com", role: "Sales", active: 1, referral_code: "nadeenmahmoud-fb1891" },
        { id: 18, name: "islam", email: "islam@gmail.com", role: "Sales", active: 1, referral_code: "islam-6fb9db" },
        { id: 19, name: "mahmoud adel", email: "mahmoudadel@gmail.com", role: "Manager", active: 1, referral_code: "mahmoudadel-49cedc" }
      ];
      return seedUsers;
    },

    getCurrentUser: function() {
      try {
        const u = sessionStorage.getItem(STORAGE_KEY_USER);
        return u ? JSON.parse(u) : null;
      } catch (e) {
        return null;
      }
    },

    setCurrentUser: function(user) {
      if (user) {
        sessionStorage.setItem(STORAGE_KEY_USER, JSON.stringify(user));
      } else {
        sessionStorage.removeItem(STORAGE_KEY_USER);
      }
    },

    logout: function() {
      sessionStorage.removeItem(STORAGE_KEY_USER);
    },

    login: function(identifier, password) {
      const clean = (identifier || '').trim().toLowerCase();
      const users = this.getUsers();

      // Check direct match by email or referral code or role name
      const user = users.find(u => 
        (u.email && u.email.toLowerCase() === clean) || 
        (u.name && u.name.toLowerCase() === clean) ||
        (u.referral_code && u.referral_code.toLowerCase() === clean)
      );

      if (user) {
        this.setCurrentUser(user);
        return { success: true, user: user };
      }

      // Quick fallback demo accounts
      if (clean === 'admin' || clean === 'admin@elamar.com') {
        const adminUser = users.find(u => u.role === 'Admin') || { id: 1, name: 'المدير التنفيذي (Admin)', email: 'admin@elamar.com', role: 'Admin' };
        this.setCurrentUser(adminUser);
        return { success: true, user: adminUser };
      }
      if (clean === 'sales' || clean.includes('sales')) {
        const salesUser = users.find(u => u.role === 'Sales') || { id: 13, name: 'hana mohamed', email: 'hanamohaed@gmail.com', role: 'Sales' };
        this.setCurrentUser(salesUser);
        return { success: true, user: salesUser };
      }
      if (clean === 'manager' || clean.includes('manager')) {
        const mgrUser = users.find(u => u.role === 'Manager') || { id: 14, name: 'emy mousa', email: 'emymousa@gmail.com', role: 'Manager' };
        this.setCurrentUser(mgrUser);
        return { success: true, user: mgrUser };
      }

      // If user typed anything with sales role default
      const defaultUser = { id: 99, name: identifier, email: identifier, role: 'Sales' };
      this.setCurrentUser(defaultUser);
      return { success: true, user: defaultUser };
    },

    getClients: function() {
      let localClients = [];
      try {
        const raw = localStorage.getItem(STORAGE_KEY_CLIENTS);
        if (raw) {
          localClients = JSON.parse(raw);
          if (!Array.isArray(localClients)) localClients = [];
        }
      } catch (e) {
        localClients = [];
      }

      const seedClients = (window.ELAMAR_SEED_DATA && window.ELAMAR_SEED_DATA.SEED_CLIENTS) ? window.ELAMAR_SEED_DATA.SEED_CLIENTS : [];

      // Merge: local submissions come first, then seed clients (avoid duplicates by ID or unit code)
      const seenUnits = new Set();
      const combined = [];

      localClients.forEach(c => {
        const key = (c.eoi_number || c.unit_code || '') + '_' + (c.name || '');
        if (key !== '_') seenUnits.add(key);
        combined.push(c);
      });

      seedClients.forEach(c => {
        const key = (c.eoi_number || c.unit_code || '') + '_' + (c.name || '');
        if (!seenUnits.has(key)) {
          seenUnits.add(key);
          combined.push(c);
        }
      });

      return combined;
    },

    saveClient: function(clientData) {
      try {
        let clients = [];
        const raw = localStorage.getItem(STORAGE_KEY_CLIENTS);
        if (raw) {
          clients = JSON.parse(raw);
          if (!Array.isArray(clients)) clients = [];
        }

        // Generate synthetic ID if needed
        if (!clientData.id) {
          clientData.id = Date.now();
        }
        if (!clientData.created_at) {
          const now = new Date();
          clientData.created_at = now.toISOString().replace('T', ' ').substring(0, 19);
        }

        clients.unshift(clientData);
        localStorage.setItem(STORAGE_KEY_CLIENTS, JSON.stringify(clients));
        return true;
      } catch (e) {
        console.error('Failed to save client to localStorage:', e);
        return false;
      }
    },

    formatNumber: function(num) {
      if (num === null || num === undefined || num === '') return '—';
      const n = Number(num);
      return isNaN(n) ? num : n.toLocaleString('en-US');
    },

    splitPartners: function(namesStr) {
      if (!namesStr) return ['-'];
      const parts = namesStr.split(/[،,]/).map(s => s.trim()).filter(s => s.length > 0);
      return parts.length > 0 ? parts : [namesStr];
    }
  };

  window.ElamarData = ElamarData;
})(window);

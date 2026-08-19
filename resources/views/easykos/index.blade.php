@extends('layouts.app')

@section('content')
<div x-data="{
    currentUser: @js(Auth::user()),
    viewMode: @js(Auth::check() ? Auth::user()->role : 'landing'),
    authModal: 'none',
    showPassword: false,
    
    // Form Inputs
    loginEmail: '',
    loginPassword: '',
    signupName: '',
    signupPhone: '',
    signupEmail: '',
    signupPassword: '',
    signupRole: 'tenant',

    // Tenant Portal State
    tenantTab: 'home', // 'home', 'saved', 'messages', 'pet', 'profile'
    tenantCategoryFilter: 'all',
    searchQuery: '',
    
    // Kos Listings (preloaded from database)
    kosListings: @js($properties),
    selectedKos: null,
    selectedKosGalleryIndex: 0,
    showKosModal: false,
    showPaymentModal: false,
    paymentMethod: 'ovo',
    voucherCodeInput: '',
    appliedDiscount: 0,
    appliedVoucherText: '',
    bookingSuccess: false,
    
    // Saved Kos
    savedKosIds: [1, 3],
    
    // Bookings (preloaded)
    myBookings: @js($bookings),

    // Owner State
    ownerTab: 'dashboard', // 'dashboard', 'properties', 'add_listing', 'messages', 'pet', 'payouts'
    ownerProperties: @js($myProperties),
    newListing: {
        name: '',
        type: 'Standard Single',
        address: '',
        price: '',
        desc: '',
        facilities: ['WiFi', 'Kamar Mandi Dalam']
    },

    // Real-Time Chat System State (Auto-Sync Polling)
    activeContactId: null,
    chatContacts: [],
    currentMessages: [],
    chatInput: '',
    isLoadingChat: false,
    chatPollTimer: null,
    lastMessageCount: 0,

    // Virtual Pet System (Time Engine with 8h Feeding & 5d Play Cycle)
    pet: @js($pet) || {
        name: 'Moni',
        level: 1,
        exp: 0,
        max_exp: 100,
        health: 100,
        hunger: 100,
        happiness: 100,
        mood: 'happy'
    },
    petFeedCountdown: '8j 0m',
    petLastFedHuman: 'Baru saja',
    petLastPlayedHuman: 'Baru saja',

    get petImage() {
        if (!this.pet) return '/images/mascot-happy.png';
        if (this.pet.hunger < 25) return '/images/mascot-hungry.png';
        if (this.pet.health < 40 || this.pet.happiness < 30) return '/images/mascot-sleepy.png';
        if (this.pet.mood === 'excited') return '/images/mascot-excited.png';
        return '/images/mascot-happy.png';
    },

    toastMsg: '',
    showToast: false,

    init() {
        if (this.currentUser) {
            this.loadChatContacts();
            this.loadPet();
            
            // Background Live Polling every 2.5 seconds for Chat & Pet Status
            if (!this.chatPollTimer) {
                this.chatPollTimer = setInterval(() => {
                    this.pollLiveChat();
                }, 2500);
            }
        }
    },

    triggerToast(message) {
        this.toastMsg = message;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3200);
    },

    requireAuthAction(actionCallback) {
        if (!this.currentUser) {
            this.authModal = 'login';
            this.triggerToast('Silakan login terlebih dahulu untuk mengakses fitur ini.');
        } else {
            actionCallback();
        }
    },

    // Auth Handlers
    async handleLogin(emailToUse, passwordToUse) {
        const email = emailToUse || this.loginEmail;
        const password = passwordToUse || this.loginPassword;

        if (!email || !password) {
            this.triggerToast('Mohon isi email dan password.');
            return;
        }

        try {
            const res = await fetch('/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ email, password })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                this.triggerToast(data.message || 'Login gagal.');
            }
        } catch(e) {
            this.triggerToast('Terjadi kesalahan sistem.');
        }
    },

    async handleRegister() {
        if (!this.signupName || !this.signupEmail || !this.signupPassword) {
            this.triggerToast('Mohon lengkapi formulir pendaftaran.');
            return;
        }

        try {
            const res = await fetch('/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    name: this.signupName,
                    email: this.signupEmail,
                    phone: this.signupPhone,
                    role: this.signupRole,
                    password: this.signupPassword
                })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                this.triggerToast(data.message || 'Pendaftaran gagal.');
            }
        } catch(e) {
            this.triggerToast('Terjadi kesalahan sistem.');
        }
    },

    async handleLogout() {
        if (this.chatPollTimer) clearInterval(this.chatPollTimer);
        try {
            await fetch('/logout', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            window.location.reload();
        } catch(e) {
            this.triggerToast('Gagal logout.');
        }
    },

    // Real-Time Chat Functions
    async loadChatContacts() {
        try {
            const res = await fetch('/api/chat/contacts');
            const data = await res.json();
            if (data.contacts) {
                this.chatContacts = data.contacts;
                if (!this.activeContactId && this.chatContacts.length > 0) {
                    this.selectContact(this.chatContacts[0].id);
                }
            }
        } catch (e) {
            console.error('Failed to load contacts', e);
        }
    },

    async selectContact(contactId) {
        this.activeContactId = contactId;
        this.isLoadingChat = true;
        try {
            const res = await fetch('/api/chat/messages/' + contactId);
            const data = await res.json();
            if (data.messages) {
                this.currentMessages = data.messages;
                this.lastMessageCount = data.messages.length;
                this.scrollToBottom();
            }
        } catch (e) {
            console.error('Failed to load messages', e);
        } finally {
            this.isLoadingChat = false;
        }
    },

    async pollLiveChat() {
        if (!this.currentUser) return;
        
        try {
            const resContacts = await fetch('/api/chat/contacts');
            const dataContacts = await resContacts.json();
            if (dataContacts.contacts) {
                this.chatContacts = dataContacts.contacts;
            }
        } catch(e) {}

        if (this.activeContactId) {
            try {
                const res = await fetch('/api/chat/messages/' + this.activeContactId);
                const data = await res.json();
                if (data.messages) {
                    const isNewMessage = data.messages.length > this.lastMessageCount;
                    this.currentMessages = data.messages;
                    this.lastMessageCount = data.messages.length;
                    
                    if (isNewMessage) {
                        this.scrollToBottom();
                    }
                }
            } catch(e) {}
        }
    },

    scrollToBottom() {
        this.$nextTick(() => {
            const container = document.getElementById('chatMessagesContainer');
            if (container) container.scrollTop = container.scrollHeight;
            const ownerContainer = document.getElementById('ownerChatMessagesContainer');
            if (ownerContainer) ownerContainer.scrollTop = ownerContainer.scrollHeight;
        });
    },

    async sendChatMessage() {
        if (!this.chatInput.trim() || !this.activeContactId) return;

        const textToSend = this.chatInput;
        this.chatInput = '';

        const tempMsg = {
            id: Date.now(),
            sender: 'me',
            text: textToSend,
            time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
        };
        this.currentMessages.push(tempMsg);
        this.lastMessageCount = this.currentMessages.length;
        this.scrollToBottom();

        try {
            const res = await fetch('/api/chat/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    receiver_id: this.activeContactId,
                    message: textToSend
                })
            });
            const data = await res.json();
            if (data.success) {
                this.pollLiveChat();
            }
        } catch(e) {
            this.triggerToast('Gagal mengirim pesan.');
        }
    },

    // Database-Driven Virtual Pet Functions
    async loadPet() {
        try {
            const res = await fetch('/api/pet');
            const data = await res.json();
            if (data.pet) {
                this.pet = data.pet;
                if (data.feed_countdown) {
                    this.petFeedCountdown = data.feed_countdown.formatted;
                    this.petLastFedHuman = data.feed_countdown.last_fed_human;
                    this.petLastPlayedHuman = data.feed_countdown.last_played_human;
                }
            }
        } catch(e) {
            console.error('Failed to load pet', e);
        }
    },

    async feedPet() {
        try {
            const res = await fetch('/api/pet/feed', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await res.json();
            if (data.pet) {
                this.pet = data.pet;
                this.loadPet();
            }
            this.triggerToast(data.message);
        } catch(e) {
            this.triggerToast('Gagal memberi makan pet.');
        }
    },

    async playPet() {
        try {
            const res = await fetch('/api/pet/play', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await res.json();
            if (data.pet) {
                this.pet = data.pet;
                this.loadPet();
            }
            this.triggerToast(data.message);
        } catch(e) {
            this.triggerToast('Gagal bermain dengan pet.');
        }
    },

    // Property Management (Owner)
    async togglePropertyStatus(prop) {
        try {
            const res = await fetch('/api/properties/' + prop.id + '/toggle-status', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await res.json();
            if (data.success) {
                prop.status = data.status;
                this.triggerToast(data.message);
            }
        } catch(e) {
            this.triggerToast('Gagal memperbarui status kos.');
        }
    },

    async saveNewListing() {
        if (!this.newListing.name || !this.newListing.price) {
            this.triggerToast('Mohon isi nama kos dan harga sewa.');
            return;
        }

        try {
            const res = await fetch('/api/properties', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(this.newListing)
            });
            const data = await res.json();
            if (data.success) {
                this.ownerProperties.unshift(data.property);
                this.kosListings.unshift(data.property);
                this.triggerToast(data.message);
                this.newListing = { name: '', type: 'Standard Single', address: '', price: '', desc: '', facilities: ['WiFi', 'Kamar Mandi Dalam'] };
                this.ownerTab = 'properties';
            }
        } catch(e) {
            this.triggerToast('Gagal menyimpan listing kos.');
        }
    },

    // Booking & Payments
    openKosDetail(kos) {
        this.selectedKos = kos;
        this.selectedKosGalleryIndex = 0;
        this.showKosModal = true;
    },

    openPayment() {
        this.requireAuthAction(() => {
            this.showKosModal = false;
            this.voucherCodeInput = '';
            this.appliedDiscount = 0;
            this.appliedVoucherText = '';
            this.showPaymentModal = true;
        });
    },

    applyVoucher() {
        const code = this.voucherCodeInput.trim().toUpperCase();
        if (code === 'VETPET100') {
            this.appliedDiscount = 100000;
            this.appliedVoucherText = 'Voucher Potongan Rp 100.000 Berhasil Diterapkan!';
            this.triggerToast('Voucher diskon Rp 100.000 aktif! 🎉');
        } else if (code === 'EASYPET10') {
            this.appliedDiscount = Math.round(this.selectedKos.price * 0.1);
            this.appliedVoucherText = 'Diskon 10% (Rp ' + this.appliedDiscount.toLocaleString('id-ID') + ') Diterapkan!';
            this.triggerToast('Voucher diskon 10% aktif! 🎉');
        } else {
            this.triggerToast('Kode voucher tidak valid.');
        }
    },

    get finalPaymentTotal() {
        if (!this.selectedKos) return 0;
        return Math.max(0, this.selectedKos.price - this.appliedDiscount);
    },

    async processPayment() {
        try {
            const res = await fetch('/api/bookings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    property_id: this.selectedKos.id,
                    duration_months: 1,
                    payment_method: this.paymentMethod,
                    voucher_code: this.appliedDiscount > 0 ? this.voucherCodeInput : null
                })
            });
            const data = await res.json();
            if (data.success) {
                this.bookingSuccess = true;
                this.myBookings.unshift(data.booking);
                this.triggerToast(data.message);
                setTimeout(() => {
                    this.showPaymentModal = false;
                    this.bookingSuccess = false;
                    this.tenantTab = 'profile';
                }, 1800);
            }
        } catch(e) {
            this.triggerToast('Gagal memproses pembayaran.');
        }
    },

    toggleSaveKos(id) {
        this.requireAuthAction(() => {
            if (this.savedKosIds.includes(id)) {
                this.savedKosIds = this.savedKosIds.filter(i => i !== id);
                this.triggerToast('Properti dihapus dari simpanan');
            } else {
                this.savedKosIds.push(id);
                this.triggerToast('Properti disimpan ke favorit!');
            }
        });
    }
}" class="relative min-h-screen pb-12">

    <!-- GLOBAL NAVBAR WITH ORIGINAL EASYKOS BRAND LOGO -->
    <header class="bg-white/95 backdrop-blur-md sticky top-0 z-40 border-b border-rose-100/60 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            
            <!-- ORIGINAL BRAND LOGO (Asset/Logo 1.png) -->
            <div class="flex items-center gap-3 cursor-pointer" @click="viewMode = (currentUser ? currentUser.role : 'landing')">
                <img src="/images/logo-full.png" alt="EasyKos Logo" class="h-10 sm:h-12 w-auto object-contain hover:scale-105 transition-transform duration-300">
            </div>

            <!-- Navigation Links -->
            <nav class="hidden md:flex items-center gap-8 font-medium text-slate-600 text-sm">
                <button @click="viewMode = (currentUser ? currentUser.role : 'landing')" class="hover:text-[#D95B3B] transition" :class="viewMode === 'landing' ? 'text-[#D95B3B] font-bold' : ''">Home</button>
                <button @click="requireAuthAction(() => { viewMode = 'tenant'; tenantTab = 'home'; })" class="hover:text-[#D95B3B] transition">Cari Kos</button>
                <button @click="requireAuthAction(() => { if (currentUser.role === 'owner') { viewMode = 'owner'; ownerTab = 'pet'; } else { viewMode = 'tenant'; tenantTab = 'pet'; } })" class="hover:text-[#D95B3B] transition flex items-center gap-1.5">
                    <img src="/images/mascot-happy.png" class="w-5 h-5 object-contain"> <span>Virtual Pet Game</span>
                </button>
                <a href="#faq" x-show="viewMode === 'landing'" class="hover:text-[#D95B3B] transition">FAQ</a>
            </nav>

            <!-- User Auth Profile Actions -->
            <div class="flex items-center gap-3">
                <template x-if="!currentUser">
                    <div class="flex items-center gap-3">
                        <button @click="authModal = 'login'" class="px-5 py-2.5 rounded-full text-slate-700 hover:text-[#D95B3B] font-semibold text-sm transition">
                            Log In
                        </button>
                        <button @click="authModal = 'role_choice'" class="terracotta-gradient-btn text-white px-6 py-2.5 rounded-full font-semibold text-sm">
                            Sign Up
                        </button>
                    </div>
                </template>

                <template x-if="currentUser">
                    <div class="flex items-center gap-3" x-data="{ openMenu: false }">
                        <button @click="viewMode = currentUser.role" class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-orange-50 border border-orange-200 text-[#D95B3B] text-xs font-bold hover:bg-orange-100 transition">
                            <i class="fa-solid" :class="currentUser.role === 'tenant' ? 'fa-user-graduate' : 'fa-building-user'"></i>
                            <span x-text="currentUser.role === 'tenant' ? 'Portal Pencari Kos' : 'Dashboard Pemilik'"></span>
                        </button>

                        <div class="relative">
                            <button @click="openMenu = !openMenu" class="flex items-center gap-2 p-1 rounded-full hover:bg-slate-100 transition">
                                <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80" class="w-9 h-9 rounded-full object-cover border-2 border-[#D95B3B]">
                                <span class="text-xs font-bold text-slate-800 hidden sm:inline" x-text="currentUser.name"></span>
                                <i class="fa-solid fa-chevron-down text-slate-400 text-xs mr-1"></i>
                            </button>

                            <div x-show="openMenu" @click.away="openMenu = false" class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-slate-100 p-2 space-y-1 text-xs">
                                <div class="p-2 border-b border-slate-100">
                                    <span class="block font-bold text-slate-900 truncate" x-text="currentUser.name"></span>
                                    <span class="text-[10px] text-slate-500 truncate" x-text="currentUser.email"></span>
                                </div>
                                <button @click="viewMode = currentUser.role; openMenu = false;" class="w-full text-left p-2 rounded-xl hover:bg-rose-50 text-slate-700 font-semibold flex items-center gap-2">
                                    <i class="fa-solid fa-gauge text-[#D95B3B]"></i> Dashboard
                                </button>
                                <button @click="handleLogout()" class="w-full text-left p-2 rounded-xl hover:bg-rose-50 text-rose-600 font-semibold flex items-center gap-2">
                                    <i class="fa-solid fa-right-from-bracket"></i> Log Out
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </header>


    <!-- ========================================== -->
    <!-- 1. LANDING PAGE VIEW                       -->
    <!-- ========================================== -->
    <div x-show="viewMode === 'landing'" x-transition:enter="transition ease-out duration-300 transform opacity-0 scale-98" x-transition:enter-end="opacity-100 scale-100">
        
        <!-- Hero Section -->
        <section id="hero" class="relative pt-12 pb-20 md:pt-20 md:pb-28 overflow-hidden bg-gradient-to-b from-[#FFF5F2] via-[#FEFDFD] to-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid lg:grid-cols-12 gap-12 items-center">
                    
                    <!-- Left Hero Content -->
                    <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-orange-100/70 border border-orange-200 text-[#D95B3B] text-xs font-semibold uppercase tracking-wider">
                            <i class="fa-solid fa-sparkles text-amber-500"></i>
                            <span>#1 Boarding Room Platform</span>
                        </div>

                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 leading-[1.15] font-['Outfit']">
                            Find Comfortable <br class="hidden sm:inline"/>
                            <span class="bg-gradient-to-r from-[#D95B3B] via-[#EE6F52] to-[#0D9488] bg-clip-text text-transparent">& Affordable Boarding.</span>
                        </h1>

                        <p class="text-slate-600 text-base sm:text-lg max-w-xl mx-auto lg:mx-0 leading-relaxed font-normal">
                            Explore the best, most affordable boarding options with complete amenities tailored for your needs. Your home away from home is just a click away.
                        </p>

                        <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4 pt-2">
                            <button @click="requireAuthAction(() => { viewMode = 'tenant'; tenantTab = 'home'; })" class="terracotta-gradient-btn text-white px-8 py-4 rounded-full font-bold text-base flex items-center gap-3 w-full sm:w-auto justify-center group shadow-lg shadow-coral-500/25">
                                <span>Search Now</span>
                                <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                            </button>
                            <button @click="authModal = 'role_choice'" class="px-8 py-4 rounded-full bg-white hover:bg-rose-50/50 text-slate-700 border border-slate-200 font-bold text-base w-full sm:w-auto shadow-sm transition">
                                Get Started
                            </button>
                        </div>

                        <!-- Stats Strip -->
                        <div class="pt-8 border-t border-slate-200/60 grid grid-cols-3 gap-6 max-w-md mx-auto lg:mx-0">
                            <div>
                                <span class="block text-2xl font-extrabold text-slate-900 font-['Outfit']">2,500+</span>
                                <span class="text-xs text-slate-500 font-medium">Kos Terverifikasi</span>
                            </div>
                            <div>
                                <span class="block text-2xl font-extrabold text-slate-900 font-['Outfit']">98%</span>
                                <span class="text-xs text-slate-500 font-medium">Penghuni Puas</span>
                            </div>
                            <div>
                                <span class="block text-2xl font-extrabold text-slate-900 font-['Outfit']">10k+</span>
                                <span class="text-xs text-slate-500 font-medium">Pengguna Aktif</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Hero Media Composite -->
                    <div class="lg:col-span-5 relative">
                        <div class="relative mx-auto max-w-md lg:max-w-none">
                            <div class="absolute -top-10 -bottom-10 -left-10 -right-10 bg-gradient-to-tr from-[#FFCCC0]/50 to-[#CCFBF3]/50 rounded-3xl filter blur-2xl -z-10"></div>

                            <div class="relative bg-white p-3 rounded-3xl shadow-2xl border border-rose-100 overflow-hidden transform lg:rotate-1 hover:rotate-0 transition duration-500">
                                <img src="https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=800&q=80" alt="Kos Modern EasyKos" class="w-full h-80 sm:h-96 object-cover rounded-2xl">
                                
                                <div class="absolute top-6 left-6 bg-white/90 backdrop-blur-md px-3.5 py-1.5 rounded-full shadow-lg border border-white flex items-center gap-2">
                                    <i class="fa-solid fa-star text-amber-400 text-sm"></i>
                                    <span class="text-xs font-bold text-slate-800">4.9 (120+ Ulasan)</span>
                                </div>

                                <div class="absolute bottom-6 right-6 bg-white/95 backdrop-blur-md p-3.5 rounded-2xl shadow-xl border border-rose-100/80 flex items-center gap-3.5">
                                    <div class="w-10 h-10 rounded-xl bg-[#FFF5F2] text-[#D95B3B] flex items-center justify-center font-bold">
                                        <i class="fa-solid fa-shield-check text-lg"></i>
                                    </div>
                                    <div>
                                        <span class="block text-xs font-bold text-slate-800">Garansi Transaksi 100%</span>
                                        <span class="text-[11px] text-slate-500">Sewa Nyaman & Aman</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-20 bg-white border-y border-slate-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-16">
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 font-['Outfit']">Why Choose EasyKos?</h2>
                    <p class="text-slate-600 text-sm sm:text-base mt-3 leading-relaxed">
                        Everything you need to find, manage, or rent out a boarding room easily, efficiently, and securely all in one platform.
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div class="bg-gradient-to-b from-[#FFF5F2]/60 to-white p-8 rounded-3xl border border-rose-100/80 card-hover-effect group">
                        <div class="w-14 h-14 rounded-2xl bg-[#D95B3B]/10 text-[#D95B3B] flex items-center justify-center text-2xl mb-6 group-hover:scale-110 group-hover:bg-[#D95B3B] group-hover:text-white transition-all">
                            <i class="fa-solid fa-house-bookmark"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 font-['Outfit'] mb-2">Room Booking & Rent</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">Easily browse, view, and book your ideal room in just a few clicks with real-time room availability status.</p>
                    </div>

                    <div class="bg-gradient-to-b from-[#FFF5F2]/60 to-white p-8 rounded-3xl border border-rose-100/80 card-hover-effect group">
                        <div class="w-14 h-14 rounded-2xl bg-[#D95B3B]/10 text-[#D95B3B] flex items-center justify-center text-2xl mb-6 group-hover:scale-110 group-hover:bg-[#D95B3B] group-hover:text-white transition-all">
                            <i class="fa-solid fa-chart-line-up"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 font-['Outfit'] mb-2">Owner Dashboard</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">Complete management system for landlords to track properties, revenue payouts, and chat with prospective tenants.</p>
                    </div>

                    <div class="bg-gradient-to-b from-[#FFF5F2]/60 to-white p-8 rounded-3xl border border-rose-100/80 card-hover-effect group">
                        <div class="w-14 h-14 rounded-2xl bg-[#D95B3B]/10 text-[#D95B3B] flex items-center justify-center text-2xl mb-6 group-hover:scale-110 group-hover:bg-[#D95B3B] group-hover:text-white transition-all">
                            <i class="fa-solid fa-credit-card"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 font-['Outfit'] mb-2">Secure Payments</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">100% safe transactions via e-wallet (OVO, DANA, GoPay) or Virtual Bank Accounts with discount voucher vouchers.</p>
                    </div>

                    <div class="bg-gradient-to-b from-[#FFF5F2]/60 to-white p-8 rounded-3xl border border-rose-100/80 card-hover-effect group">
                        <div class="w-14 h-14 rounded-2xl bg-[#0D9488]/10 text-[#0D9488] flex items-center justify-center text-2xl mb-6 group-hover:scale-110 group-hover:bg-[#0D9488] group-hover:text-white transition-all">
                            <i class="fa-solid fa-comments"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 font-['Outfit'] mb-2">Real-Time Live Chat</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">Connect directly between room owners and prospective tenants with automatic live background sync without manual refreshes!</p>
                    </div>

                    <div class="bg-gradient-to-b from-[#FFF5F2]/60 to-white p-8 rounded-3xl border border-rose-100/80 card-hover-effect group">
                        <div class="w-14 h-14 rounded-2xl bg-[#0D9488]/10 text-[#0D9488] flex items-center justify-center text-2xl mb-6 group-hover:scale-110 group-hover:bg-[#0D9488] group-hover:text-white transition-all">
                            <i class="fa-solid fa-circle-play"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 font-['Outfit'] mb-2">Multi-Photo Gallery</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">Inspect every angle of room interiors and bathrooms through thumbnail photo galleries.</p>
                    </div>

                    <div class="bg-gradient-to-b from-[#FFF5F2]/60 to-white p-8 rounded-3xl border border-rose-100/80 card-hover-effect group">
                        <div class="w-14 h-14 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 group-hover:bg-amber-500 group-hover:text-white transition-all">
                            <img src="/images/mascot-happy.png" class="w-12 h-12 object-contain">
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 font-['Outfit'] mb-2">Virtual Pet for All Users</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">Both Tenants and Landlords can care for cute Pet Moni with 8-hour feeding and 5-day happiness cycles!</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section id="faq" class="py-20 bg-white border-t border-slate-100" x-data="{ openFaq: 1 }">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <h2 class="text-3xl font-extrabold text-slate-900 font-['Outfit']">Frequently Asked Questions</h2>
                    <p class="text-slate-500 text-sm mt-2">Get answers to common questions about EasyKos</p>
                </div>

                <div class="space-y-4">
                    <div class="border border-slate-200 rounded-2xl overflow-hidden">
                        <button @click="openFaq = openFaq === 1 ? null : 1" class="w-full px-6 py-4 text-left font-bold text-slate-800 flex items-center justify-between bg-slate-50/50 hover:bg-slate-100/50 transition">
                            <span>Bagaimana cara kerja siklus makan & main Virtual Pet?</span>
                            <i class="fa-solid fa-chevron-down text-slate-400 transition-transform" :class="openFaq === 1 ? 'rotate-180 text-[#D95B3B]' : ''"></i>
                        </button>
                        <div x-show="openFaq === 1" x-collapse class="px-6 py-4 text-slate-600 text-sm leading-relaxed border-t border-slate-100 bg-white">
                            Pet membutuhkan makan 3 kali sehari (siklus kenyang bertahan selama 8 jam). Jika lewat dari 8 jam belum diberi makan, kesehatan pet akan mulai berkurang. Sementara kebahagiaan bertahan lebih lama (berkurang bertahap dalam 5 hari jika tidak diajak bermain). Waktu dihitung akurat menggunakan jam database!
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-slate-900 text-slate-300 pt-16 pb-12 border-t border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid md:grid-cols-12 gap-8 pb-12 border-b border-slate-800">
                    <div class="md:col-span-6 space-y-4">
                        <img src="/images/logo-full.png" alt="EasyKos Logo" class="h-10 w-auto object-contain brightness-150">
                        <p class="text-slate-400 text-sm max-w-sm leading-relaxed">
                            Platform sewa kos terpercaya #1 yang memberikan pengalaman mencari dan mengelola hunian impian dengan mudah dan menyenangkan.
                        </p>
                    </div>
                    <div class="md:col-span-6 text-right space-y-2 text-xs text-slate-400">
                        <p>Kontak: hi@easykos.com | +62 812-3456-7890</p>
                        <p>Sleman, Daerah Istimewa Yogyakarta</p>
                    </div>
                </div>
                <div class="pt-8 text-center text-xs text-slate-500">
                    © 2026 EasyKos Platform. All rights reserved.
                </div>
            </div>
        </footer>
    </div>


    <!-- ========================================== -->
    <!-- 2. TENANT PORTAL VIEW                      -->
    <!-- ========================================== -->
    <div x-show="viewMode === 'tenant'" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        <div class="grid lg:grid-cols-12 gap-8">
            
            <!-- Left Sidebar for Tenant -->
            <div class="lg:col-span-3">
                <div class="bg-white p-5 rounded-3xl border border-rose-100/80 shadow-sm sticky top-[95px] space-y-6">
                    <div class="flex items-center gap-3.5 p-3 rounded-2xl bg-[#FFF5F2]/80 border border-rose-100">
                        <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80" class="w-12 h-12 rounded-xl object-cover border-2 border-white shadow-sm">
                        <div class="overflow-hidden">
                            <h4 class="font-bold text-slate-900 text-sm font-['Outfit'] truncate" x-text="currentUser ? currentUser.name : 'Pencari Kos'"></h4>
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#D95B3B] bg-orange-100/80 px-2 py-0.5 rounded-md">
                                <i class="fa-solid fa-user-graduate"></i> Penyewa Aktif
                            </span>
                        </div>
                    </div>

                    <nav class="space-y-1">
                        <button @click="tenantTab = 'home'" 
                                :class="tenantTab === 'home' ? 'bg-[#D95B3B] text-white font-bold shadow-md' : 'text-slate-600 hover:bg-rose-50/50 hover:text-[#D95B3B]'"
                                class="w-full px-4 py-3 rounded-xl text-left text-sm font-medium transition flex items-center justify-between">
                            <span class="flex items-center gap-3"><i class="fa-solid fa-house w-5"></i> <span>Home / Cari Kos</span></span>
                            <i class="fa-solid fa-chevron-right text-xs opacity-60"></i>
                        </button>

                        <button @click="tenantTab = 'saved'" 
                                :class="tenantTab === 'saved' ? 'bg-[#D95B3B] text-white font-bold shadow-md' : 'text-slate-600 hover:bg-rose-50/50 hover:text-[#D95B3B]'"
                                class="w-full px-4 py-3 rounded-xl text-left text-sm font-medium transition flex items-center justify-between">
                            <span class="flex items-center gap-3"><i class="fa-solid fa-heart w-5 text-rose-500"></i> <span>Kos Tersimpan</span></span>
                            <span x-text="savedKosIds.length" class="text-xs bg-rose-100 text-rose-600 px-2 py-0.5 rounded-full font-bold"></span>
                        </button>

                        <button @click="tenantTab = 'messages'; loadChatContacts();" 
                                :class="tenantTab === 'messages' ? 'bg-[#D95B3B] text-white font-bold shadow-md' : 'text-slate-600 hover:bg-rose-50/50 hover:text-[#D95B3B]'"
                                class="w-full px-4 py-3 rounded-xl text-left text-sm font-medium transition flex items-center justify-between">
                            <span class="flex items-center gap-3"><i class="fa-solid fa-comments w-5 text-teal-500"></i> <span>Pesan / Chat</span></span>
                            <span class="inline-flex items-center gap-1 text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full font-bold">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Live
                            </span>
                        </button>

                        <button @click="tenantTab = 'pet'; loadPet();" 
                                :class="tenantTab === 'pet' ? 'bg-gradient-to-r from-amber-500 to-[#D95B3B] text-white font-bold shadow-md' : 'text-slate-600 hover:bg-rose-50/50 hover:text-amber-600'"
                                class="w-full px-4 py-3 rounded-xl text-left text-sm font-medium transition flex items-center justify-between">
                            <span class="flex items-center gap-3"><img :src="petImage" class="w-5 h-5 object-contain"> <span>Virtual Pet Game</span></span>
                            <span class="text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-bold">Lv. <span x-text="pet.level"></span></span>
                        </button>

                        <button @click="tenantTab = 'profile'" 
                                :class="tenantTab === 'profile' ? 'bg-[#D95B3B] text-white font-bold shadow-md' : 'text-slate-600 hover:bg-rose-50/50 hover:text-[#D95B3B]'"
                                class="w-full px-4 py-3 rounded-xl text-left text-sm font-medium transition flex items-center justify-between">
                            <span class="flex items-center gap-3"><i class="fa-solid fa-user-circle w-5 text-slate-500"></i> <span>Profil & Booking</span></span>
                        </button>
                    </nav>

                    <!-- Mini Pet Widget with Dynamic Hunger & Happiness status -->
                    <div class="bg-gradient-to-br from-amber-50 to-orange-50/80 p-4 rounded-2xl border border-amber-200/60 text-center space-y-2">
                        <div class="w-20 h-20 mx-auto relative animate-pet-bob">
                            <img :src="petImage" class="w-full h-full object-contain filter drop-shadow-md">
                        </div>
                        <div class="space-y-0.5">
                            <h5 class="text-xs font-bold text-slate-800 font-['Outfit']">Pet <span x-text="pet.name"></span> (Lv. <span x-text="pet.level"></span>)</h5>
                            <span class="text-[10px] text-amber-800 block font-semibold">Makan dlm: <span x-text="petFeedCountdown"></span></span>
                        </div>
                        <button @click="tenantTab = 'pet'; loadPet();" class="w-full py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition">
                            Rawat Pet & Klaim Voucher
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Area for Tenant -->
            <div class="lg:col-span-9 space-y-6">
                
                <!-- TAB 1: TENANT HOME -->
                <div x-show="tenantTab === 'home'" class="space-y-6">
                    <div class="bg-white p-3 rounded-2xl border border-rose-100 shadow-xs flex items-center gap-3">
                        <i class="fa-solid fa-magnifying-glass text-slate-400 text-sm ml-2"></i>
                        <input type="text" x-model="searchQuery" placeholder="Cari nama kos, lokasi (Gejayan, Seturan, Pogung), atau tipe kamar..." class="flex-1 text-xs focus:outline-none text-slate-800 bg-transparent">
                        <button x-show="searchQuery" @click="searchQuery = ''" class="text-slate-400 hover:text-slate-600 text-xs mr-2">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <!-- Promo Banner -->
                    <div class="relative overflow-hidden bg-gradient-to-r from-[#D95B3B] via-[#EE6F52] to-[#C84628] rounded-3xl p-6 sm:p-8 text-white shadow-xl flex items-center justify-between">
                        <div class="relative z-10 max-w-lg space-y-3">
                            <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-bold tracking-wider uppercase backdrop-blur-sm">Database Gamification System</span>
                            <h3 class="text-2xl sm:text-3xl font-extrabold font-['Outfit'] leading-tight">
                                Rawat Pet Tiap 8 Jam! Dapatkan Voucher Sewa!
                            </h3>
                            <p class="text-rose-100 text-xs sm:text-sm">
                                Beri makan Pet Moni 3x sehari (siklus 8 jam) dan ajak bermain agar tetap bahagia & buka voucher diskon kos!
                            </p>
                            <div class="pt-2">
                                <button @click="tenantTab = 'pet'; loadPet();" class="bg-white text-[#D95B3B] hover:bg-rose-50 px-6 py-2.5 rounded-full font-bold text-xs sm:text-sm shadow-md transition">
                                    Buka Game Pet <i class="fa-solid fa-gamepad ml-1"></i>
                                </button>
                            </div>
                        </div>
                        <div class="hidden sm:block w-36 h-36">
                            <img :src="petImage" class="w-full h-full object-contain filter drop-shadow-xl animate-pet-happy">
                        </div>
                    </div>

                    <!-- Category Filters -->
                    <div class="flex items-center justify-between flex-wrap gap-4 pt-2">
                        <h3 class="text-xl font-bold text-slate-900 font-['Outfit']">Rekomendasi Kos Terdekat</h3>
                        <div class="flex items-center gap-2 overflow-x-auto pb-1">
                            <button @click="tenantCategoryFilter = 'all'" :class="tenantCategoryFilter === 'all' ? 'bg-[#D95B3B] text-white font-bold' : 'bg-white text-slate-600 border border-slate-200'" class="px-4 py-1.5 rounded-full text-xs transition">Semua</button>
                            <button @click="tenantCategoryFilter = 'single'" :class="tenantCategoryFilter === 'single' ? 'bg-[#D95B3B] text-white font-bold' : 'bg-white text-slate-600 border border-slate-200'" class="px-4 py-1.5 rounded-full text-xs transition">Single Bed</button>
                            <button @click="tenantCategoryFilter = 'deluxe'" :class="tenantCategoryFilter === 'deluxe' ? 'bg-[#D95B3B] text-white font-bold' : 'bg-white text-slate-600 border border-slate-200'" class="px-4 py-1.5 rounded-full text-xs transition">Deluxe AC</button>
                            <button @click="tenantCategoryFilter = 'premium'" :class="tenantCategoryFilter === 'premium' ? 'bg-[#D95B3B] text-white font-bold' : 'bg-white text-slate-600 border border-slate-200'" class="px-4 py-1.5 rounded-full text-xs transition">Premium Suite</button>
                        </div>
                    </div>

                    <!-- Kos Listings Grid -->
                    <div class="grid sm:grid-cols-2 gap-6">
                        <template x-for="kos in kosListings.filter(k => (tenantCategoryFilter === 'all' || k.category === tenantCategoryFilter) && (!searchQuery || k.name.toLowerCase().includes(searchQuery.toLowerCase()) || k.address.toLowerCase().includes(searchQuery.toLowerCase()) || k.type.toLowerCase().includes(searchQuery.toLowerCase())))" :key="kos.id">
                            <div class="bg-white rounded-3xl border border-rose-100/80 overflow-hidden card-hover-effect flex flex-col justify-between">
                                <div>
                                    <div class="relative">
                                        <img :src="kos.image" :alt="kos.name" class="w-full h-48 object-cover">
                                        <button @click="toggleSaveKos(kos.id)" class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/90 backdrop-blur-md flex items-center justify-center text-rose-500 shadow-md hover:scale-110 transition">
                                            <i :class="savedKosIds.includes(kos.id) ? 'fa-solid fa-heart' : 'fa-regular fa-heart'"></i>
                                        </button>
                                        <div class="absolute bottom-3 left-3 bg-slate-900/80 backdrop-blur-md text-white px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1.5">
                                            <i class="fa-solid fa-star text-amber-400"></i>
                                            <span x-text="kos.rating"></span> (<span x-text="kos.reviews_count"></span>)
                                        </div>
                                    </div>
                                    <div class="p-5 space-y-2">
                                        <span class="text-[11px] font-bold text-[#D95B3B] uppercase tracking-wider bg-orange-50 px-2.5 py-1 rounded-md" x-text="kos.type"></span>
                                        <h4 class="text-lg font-bold text-slate-900 font-['Outfit'] truncate" x-text="kos.name"></h4>
                                        <p class="text-slate-500 text-xs flex items-center gap-1.5">
                                            <i class="fa-solid fa-location-dot text-slate-400"></i>
                                            <span x-text="kos.address"></span>
                                        </p>
                                    </div>
                                </div>

                                <div class="px-5 pb-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                                    <div>
                                        <span class="text-[10px] text-slate-400 block font-medium">Mulai dari</span>
                                        <span class="text-base font-extrabold text-[#D95B3B] font-['Outfit']">Rp <span x-text="kos.price.toLocaleString('id-ID')"></span></span> <span class="text-[11px] text-slate-400">/bln</span>
                                    </div>
                                    <button @click="openKosDetail(kos)" class="terracotta-gradient-btn text-white px-4 py-2 rounded-xl text-xs font-bold">
                                        Lihat Detail
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- TAB 2: SAVED -->
                <div x-show="tenantTab === 'saved'" class="space-y-6">
                    <h3 class="text-xl font-bold text-slate-900 font-['Outfit']">Kos Tersimpan (<span x-text="savedKosIds.length"></span>)</h3>
                    <div class="grid sm:grid-cols-2 gap-6">
                        <template x-for="kos in kosListings.filter(k => savedKosIds.includes(k.id))" :key="kos.id">
                            <div class="bg-white rounded-3xl border border-rose-100/80 overflow-hidden card-hover-effect">
                                <img :src="kos.image" :alt="kos.name" class="w-full h-44 object-cover">
                                <div class="p-5 space-y-2">
                                    <h4 class="text-lg font-bold text-slate-900 font-['Outfit']" x-text="kos.name"></h4>
                                    <p class="text-slate-500 text-xs" x-text="kos.address"></p>
                                    <div class="flex items-center justify-between pt-3">
                                        <span class="text-base font-bold text-[#D95B3B]">Rp <span x-text="kos.price.toLocaleString('id-ID')"></span>/bln</span>
                                        <button @click="openKosDetail(kos)" class="terracotta-gradient-btn text-white px-4 py-2 rounded-xl text-xs font-bold">Book Now</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- TAB 3: REAL CHAT INTERACTION -->
                <div x-show="tenantTab === 'messages'" class="bg-white rounded-3xl border border-rose-100 overflow-hidden shadow-sm grid lg:grid-cols-12 min-h-[550px]">
                    <div class="lg:col-span-4 border-r border-slate-100 p-4 space-y-3 bg-slate-50/50">
                        <div class="flex items-center justify-between px-2">
                            <h4 class="font-bold text-slate-900 text-sm font-['Outfit']">Daftar Kontak Pemilik</h4>
                            <span class="inline-flex items-center gap-1 text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Live Auto-Sync
                            </span>
                        </div>
                        <div class="space-y-1">
                            <template x-for="contact in chatContacts" :key="contact.id">
                                <div @click="selectContact(contact.id)" 
                                     :class="activeContactId === contact.id ? 'bg-white border-[#D95B3B] shadow-xs' : 'border-transparent hover:bg-white/80'"
                                     class="p-3 rounded-2xl border flex items-center gap-3 cursor-pointer transition">
                                    <div class="relative">
                                        <img :src="contact.avatar" class="w-11 h-11 rounded-full object-cover">
                                        <span x-show="contact.online" class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-500 border-2 border-white"></span>
                                    </div>
                                    <div class="overflow-hidden flex-1">
                                        <div class="flex items-center justify-between">
                                            <h5 class="text-xs font-bold text-slate-900" x-text="contact.name"></h5>
                                            <span class="text-[10px] text-slate-400" x-text="contact.last_time"></span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 truncate" x-text="contact.subtitle"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="lg:col-span-8 flex flex-col justify-between h-full bg-white">
                        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/30">
                            <div class="flex items-center gap-3">
                                <img :src="chatContacts.find(c => c.id === activeContactId)?.avatar || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=150&q=80'" class="w-10 h-10 rounded-full object-cover">
                                <div>
                                    <h5 class="text-xs font-bold text-slate-900" x-text="chatContacts.find(c => c.id === activeContactId)?.name || 'Pilih Kontak'"></h5>
                                    <span class="text-[10px] text-emerald-600 font-semibold flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span> Real-Time Connected
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div id="chatMessagesContainer" class="p-6 space-y-4 overflow-y-auto max-h-[380px] scroll-smooth">
                            <template x-for="msg in currentMessages" :key="msg.id">
                                <div class="flex flex-col" :class="msg.sender === 'me' ? 'items-end' : 'items-start'">
                                    <div class="max-w-xs sm:max-w-md px-4 py-3 rounded-2xl text-xs leading-relaxed shadow-xs"
                                         :class="msg.sender === 'me' ? 'bg-gradient-to-r from-[#D95B3B] to-[#C84628] text-white rounded-br-none' : 'bg-slate-100 text-slate-800 rounded-bl-none border border-slate-200'">
                                        <p x-text="msg.text"></p>
                                    </div>
                                    <span class="text-[9px] text-slate-400 mt-1 px-1" x-text="msg.time"></span>
                                </div>
                            </template>
                        </div>

                        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center gap-2">
                            <input type="text" x-model="chatInput" @keydown.enter="sendChatMessage()" placeholder="Ketik pesan untuk pemilik..." class="flex-1 bg-white border border-slate-200 rounded-full px-4 py-2.5 text-xs focus:outline-none focus:border-[#D95B3B]">
                            <button @click="sendChatMessage()" class="terracotta-gradient-btn text-white w-9 h-9 rounded-full flex items-center justify-center text-xs">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: VIRTUAL PET GAME WITH REAL-TIME TIME ENGINE -->
                <div x-show="tenantTab === 'pet'" class="space-y-6">
                    <div class="bg-gradient-to-br from-amber-500 via-orange-500 to-[#D95B3B] p-8 rounded-3xl text-white shadow-xl relative overflow-hidden">
                        <div class="grid md:grid-cols-12 gap-8 items-center relative z-10">
                            
                            <!-- Left: Pet Mascot Avatar -->
                            <div class="md:col-span-5 text-center">
                                <div class="relative w-56 h-56 mx-auto bg-white/20 backdrop-blur-md rounded-3xl p-4 border-2 border-white/40 shadow-2xl flex items-center justify-center">
                                    <div class="w-full h-full bg-white/10 rounded-2xl flex flex-col items-center justify-center p-2 text-white shadow-inner relative" :class="pet.mood === 'excited' ? 'animate-pet-happy' : 'animate-pet-bob'">
                                        <img :src="petImage" class="w-36 h-36 object-contain filter drop-shadow-2xl">
                                        <span class="text-xs font-bold mt-1 font-['Outfit']">Pet <span x-text="pet.name"></span></span>
                                        <span class="text-[10px] bg-white/25 px-2.5 py-0.5 rounded-full font-bold" x-text="pet.hunger < 25 ? '🥜 Kelaparan Menangis' : (pet.health < 40 ? '😴 Sakit / Lemas' : (pet.happiness < 30 ? '🥺 Sedih / Bosan' : (pet.mood === 'excited' ? '✨ Sangat Gembira!' : '😊 Sehat & Ceria')))"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Pet 4 Core Bars (Health, Hunger 8h, Happiness 5d, EXP) -->
                            <div class="md:col-span-7 space-y-3.5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-xs text-amber-100 font-bold uppercase tracking-wider flex items-center gap-1.5">
                                            <i class="fa-solid fa-clock-rotate-left"></i> Real Time-Engine Database
                                        </span>
                                        <h3 class="text-3xl font-extrabold font-['Outfit']">Pet <span x-text="pet.name"></span></h3>
                                    </div>
                                    <div class="bg-white/20 backdrop-blur-md px-4 py-2 rounded-2xl border border-white/30 text-right">
                                        <span class="text-[10px] block text-amber-100">Level Pet</span>
                                        <span class="text-2xl font-extrabold font-['Outfit']" x-text="pet.level"></span>
                                    </div>
                                </div>

                                <!-- Bar 1: EXP Progress -->
                                <div class="space-y-1">
                                    <div class="flex justify-between text-xs font-bold text-amber-100">
                                        <span><i class="fa-solid fa-star text-amber-300 mr-1"></i> EXP Progress Level</span>
                                        <span><span x-text="pet.exp"></span> / <span x-text="pet.max_exp"></span></span>
                                    </div>
                                    <div class="w-full h-3 bg-black/20 rounded-full overflow-hidden p-0.5">
                                        <div class="h-full bg-amber-300 rounded-full transition-all duration-500 shadow-sm" :style="'width: ' + (pet.exp/pet.max_exp*100) + '%'"></div>
                                    </div>
                                </div>

                                <!-- Bar 2: Health & Hunger -->
                                <div class="grid grid-cols-2 gap-3 pt-1">
                                    <div class="bg-black/10 p-3 rounded-2xl border border-white/10 space-y-1">
                                        <div class="flex justify-between text-[11px] font-bold text-amber-100">
                                            <span><i class="fa-solid fa-heart text-rose-300 mr-1"></i> Kesehatan</span>
                                            <span x-text="pet.health + '%'"></span>
                                        </div>
                                        <div class="w-full h-2 bg-black/20 rounded-full overflow-hidden">
                                            <div class="h-full bg-rose-400 rounded-full transition-all duration-300" :style="'width: ' + pet.health + '%'"></div>
                                        </div>
                                        <span class="text-[9px] text-amber-100/80 block">Berkurang jika kelaparan > 8 jam</span>
                                    </div>

                                    <div class="bg-black/10 p-3 rounded-2xl border border-white/10 space-y-1">
                                        <div class="flex justify-between text-[11px] font-bold text-amber-100">
                                            <span><i class="fa-solid fa-drumstick-bite text-orange-300 mr-1"></i> Kekenyangan</span>
                                            <span x-text="pet.hunger + '%'"></span>
                                        </div>
                                        <div class="w-full h-2 bg-black/20 rounded-full overflow-hidden">
                                            <div class="h-full bg-emerald-400 rounded-full transition-all duration-300" :style="'width: ' + pet.hunger + '%'"></div>
                                        </div>
                                        <span class="text-[9px] text-amber-100/80 block">Makan dlm: <span x-text="petFeedCountdown"></span></span>
                                    </div>
                                </div>

                                <!-- Bar 3: Kebahagiaan (Happiness 5 Days Cycle) -->
                                <div class="bg-black/10 p-3 rounded-2xl border border-white/10 space-y-1">
                                    <div class="flex justify-between text-[11px] font-bold text-amber-100">
                                        <span><i class="fa-solid fa-face-smile-beam text-yellow-300 mr-1"></i> Tingkat Kebahagiaan & Kesenangan</span>
                                        <span x-text="pet.happiness + '%'"></span>
                                    </div>
                                    <div class="w-full h-2 bg-black/20 rounded-full overflow-hidden">
                                        <div class="h-full bg-yellow-400 rounded-full transition-all duration-300" :style="'width: ' + pet.happiness + '%'"></div>
                                    </div>
                                    <span class="text-[9px] text-amber-100/80 block">Bertahan selama 5 hari (120 jam) jika tidak diajak bermain</span>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center gap-3 pt-1">
                                    <button @click="feedPet()" class="flex-1 py-3 bg-white text-slate-900 hover:bg-amber-50 font-bold rounded-2xl text-xs shadow-lg transition flex items-center justify-center gap-2">
                                        <i class="fa-solid fa-apple-whole text-emerald-600 text-sm"></i> Beri Makan (+40% Kenyangan)
                                    </button>
                                    <button @click="playPet()" class="flex-1 py-3 bg-slate-900 text-white hover:bg-slate-800 font-bold rounded-2xl text-xs shadow-lg transition flex items-center justify-center gap-2">
                                        <i class="fa-solid fa-futbol text-amber-400 text-sm"></i> Ajak Bermain (+35% Bahagia)
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Voucher Unlocked Cards -->
                    <div class="bg-white p-6 rounded-3xl border border-rose-100 space-y-4">
                        <h4 class="font-bold text-slate-900 font-['Outfit'] text-lg">Voucher Hadiah Virtual Pet</h4>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="p-4 rounded-2xl bg-gradient-to-r from-orange-50 to-rose-50 border border-orange-200 flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-bold text-[#D95B3B] block">KODE: <span class="font-mono bg-white px-1.5 py-0.5 rounded border border-orange-300">VETPET100</span></span>
                                    <h5 class="text-lg font-extrabold text-slate-900 font-['Outfit']">Diskon Rp 100.000</h5>
                                    <span class="text-[10px] text-slate-500">Berlaku untuk semua kos</span>
                                </div>
                                <button @click="triggerToast('Kode VETPET100 tersalin!')" class="terracotta-gradient-btn text-white px-3.5 py-1.5 rounded-xl text-xs font-bold">
                                    Salin Kode
                                </button>
                            </div>
                            <div class="p-4 rounded-2xl bg-gradient-to-r from-teal-50 to-emerald-50 border border-teal-200 flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-bold text-teal-700 block">KODE: <span class="font-mono bg-white px-1.5 py-0.5 rounded border border-teal-300">EASYPET10</span></span>
                                    <h5 class="text-lg font-extrabold text-slate-900 font-['Outfit']">Cashback 10%</h5>
                                    <span class="text-[10px] text-slate-500">Bebas biaya admin</span>
                                </div>
                                <button @click="triggerToast('Kode EASYPET10 tersalin!')" class="bg-teal-600 hover:bg-teal-700 text-white px-3.5 py-1.5 rounded-xl text-xs font-bold transition">
                                    Salin Kode
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 5: PROFILE -->
                <div x-show="tenantTab === 'profile'" class="space-y-6">
                    <div class="bg-white p-8 rounded-3xl border border-rose-100 shadow-sm space-y-6">
                        <div class="flex items-center gap-4 pb-6 border-b border-slate-100">
                            <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80" class="w-16 h-16 rounded-2xl object-cover border-2 border-[#D95B3B]">
                            <div>
                                <h3 class="text-xl font-bold text-slate-900 font-['Outfit']" x-text="currentUser ? currentUser.name : ''"></h3>
                                <p class="text-xs text-slate-500" x-text="currentUser ? currentUser.email : ''"></p>
                                <span class="inline-block text-[10px] font-bold text-[#D95B3B] bg-orange-50 px-2 py-0.5 rounded mt-1">Penyewa Terverifikasi</span>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h4 class="font-bold text-slate-900 text-sm font-['Outfit']">Riwayat Booking Kos (Database SQLite)</h4>
                            <div class="space-y-3">
                                <template x-for="b in myBookings" :key="b.id">
                                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between flex-wrap gap-4">
                                        <div class="flex items-center gap-3">
                                            <img :src="b.property ? b.property.image : 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=150&q=80'" class="w-14 h-14 rounded-xl object-cover">
                                            <div>
                                                <h5 class="text-sm font-bold text-slate-900" x-text="b.property ? b.property.name : 'Kos EasyKos'"></h5>
                                                <span class="text-xs text-slate-500">Total: Rp <span x-text="b.total_price.toLocaleString('id-ID')"></span> via <span class="uppercase font-bold" x-text="b.payment_method"></span></span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="bg-emerald-100 text-emerald-700 text-xs px-3 py-1 rounded-full font-bold" x-text="b.status"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>


    <!-- ========================================== -->
    <!-- 3. OWNER DASHBOARD VIEW                    -->
    <!-- ========================================== -->
    <div x-show="viewMode === 'owner'" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        <div class="grid lg:grid-cols-12 gap-8">
            
            <div class="lg:col-span-3">
                <div class="bg-white p-5 rounded-3xl border border-teal-100 shadow-sm sticky top-[95px] space-y-6">
                    <div class="flex items-center gap-3.5 p-3 rounded-2xl bg-teal-50/80 border border-teal-100">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=150&q=80" class="w-12 h-12 rounded-xl object-cover border-2 border-white shadow-sm">
                        <div class="overflow-hidden">
                            <h4 class="font-bold text-slate-900 text-sm font-['Outfit'] truncate" x-text="currentUser ? currentUser.name : 'Pak Supri'"></h4>
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-teal-700 bg-teal-100 px-2 py-0.5 rounded-md">
                                <i class="fa-solid fa-building-user"></i> Property Owner
                            </span>
                        </div>
                    </div>

                    <nav class="space-y-1">
                        <button @click="ownerTab = 'dashboard'" 
                                :class="ownerTab === 'dashboard' ? 'bg-[#0D9488] text-white font-bold shadow-md' : 'text-slate-600 hover:bg-teal-50 hover:text-teal-700'"
                                class="w-full px-4 py-3 rounded-xl text-left text-sm font-medium transition flex items-center justify-between">
                            <span class="flex items-center gap-3"><i class="fa-solid fa-chart-pie w-5"></i> <span>Dashboard Stats</span></span>
                        </button>

                        <button @click="ownerTab = 'properties'" 
                                :class="ownerTab === 'properties' ? 'bg-[#0D9488] text-white font-bold shadow-md' : 'text-slate-600 hover:bg-teal-50 hover:text-teal-700'"
                                class="w-full px-4 py-3 rounded-xl text-left text-sm font-medium transition flex items-center justify-between">
                            <span class="flex items-center gap-3"><i class="fa-solid fa-house-chimney w-5"></i> <span>Kelola Properti</span></span>
                            <span x-text="ownerProperties.length" class="text-xs bg-teal-100 text-teal-700 px-2 py-0.5 rounded-full font-bold"></span>
                        </button>

                        <button @click="ownerTab = 'add_listing'" 
                                :class="ownerTab === 'add_listing' ? 'bg-[#0D9488] text-white font-bold shadow-md' : 'text-slate-600 hover:bg-teal-50 hover:text-teal-700'"
                                class="w-full px-4 py-3 rounded-xl text-left text-sm font-medium transition flex items-center justify-between">
                            <span class="flex items-center gap-3"><i class="fa-solid fa-circle-plus w-5"></i> <span>Tambah Listing Kos</span></span>
                        </button>

                        <!-- OWNER REAL CHAT TAB -->
                        <button @click="ownerTab = 'messages'; loadChatContacts();" 
                                :class="ownerTab === 'messages' ? 'bg-[#0D9488] text-white font-bold shadow-md' : 'text-slate-600 hover:bg-teal-50 hover:text-teal-700'"
                                class="w-full px-4 py-3 rounded-xl text-left text-sm font-medium transition flex items-center justify-between">
                            <span class="flex items-center gap-3"><i class="fa-solid fa-comments w-5"></i> <span>Pesan / Chat Calon Penyewa</span></span>
                            <span class="inline-flex items-center gap-1 text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full font-bold">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Live
                            </span>
                        </button>

                        <!-- OWNER PET TAB -->
                        <button @click="ownerTab = 'pet'; loadPet();" 
                                :class="ownerTab === 'pet' ? 'bg-gradient-to-r from-amber-500 to-[#D95B3B] text-white font-bold shadow-md' : 'text-slate-600 hover:bg-amber-50 hover:text-amber-700'"
                                class="w-full px-4 py-3 rounded-xl text-left text-sm font-medium transition flex items-center justify-between">
                            <span class="flex items-center gap-3"><img :src="petImage" class="w-5 h-5 object-contain"> <span>Virtual Pet Game</span></span>
                            <span class="text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-bold">Lv. <span x-text="pet.level"></span></span>
                        </button>

                        <button @click="ownerTab = 'payouts'" 
                                :class="ownerTab === 'payouts' ? 'bg-[#0D9488] text-white font-bold shadow-md' : 'text-slate-600 hover:bg-teal-50 hover:text-teal-700'"
                                class="w-full px-4 py-3 rounded-xl text-left text-sm font-medium transition flex items-center justify-between">
                            <span class="flex items-center gap-3"><i class="fa-solid fa-file-invoice-dollar w-5"></i> <span>Laporan Keuangan</span></span>
                        </button>
                    </nav>

                    <!-- Mini Pet Widget for Owner -->
                    <div class="bg-gradient-to-br from-teal-50 to-emerald-50/80 p-4 rounded-2xl border border-teal-200/60 text-center space-y-2">
                        <div class="w-20 h-20 mx-auto relative animate-pet-bob">
                            <img :src="petImage" class="w-full h-full object-contain filter drop-shadow-md">
                        </div>
                        <div class="space-y-0.5">
                            <h5 class="text-xs font-bold text-slate-800 font-['Outfit']">Pet <span x-text="pet.name"></span> (Lv. <span x-text="pet.level"></span>)</h5>
                            <span class="text-[10px] text-teal-800 block font-semibold">Makan dlm: <span x-text="petFeedCountdown"></span></span>
                        </div>
                        <button @click="ownerTab = 'pet'; loadPet();" class="w-full py-1.5 rounded-lg bg-[#0D9488] hover:bg-[#0F766E] text-white text-xs font-bold transition">
                            Rawat Pet & Boost Kos
                        </button>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-9 space-y-6">
                
                <!-- OWNER DASHBOARD STATS -->
                <div x-show="ownerTab === 'dashboard'" class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-2xl font-bold text-slate-900 font-['Outfit']">Overview Ringkasan Properti</h3>
                        <button @click="ownerTab = 'add_listing'" class="bg-[#0D9488] hover:bg-[#0F766E] text-white px-5 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-2">
                            <i class="fa-solid fa-plus"></i> Tambah Kos Baru
                        </button>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-6">
                        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-2">
                            <span class="text-xs text-slate-500 font-bold uppercase tracking-wider block">Total Pendapatan</span>
                            <h4 class="text-2xl font-extrabold text-slate-900 font-['Outfit']">Rp 15.000.000</h4>
                            <span class="text-[11px] text-emerald-600 font-semibold"><i class="fa-solid fa-arrow-trend-up"></i> +12% dari bulan lalu</span>
                        </div>
                        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-2">
                            <span class="text-xs text-slate-500 font-bold uppercase tracking-wider block">Kamar Terisi</span>
                            <h4 class="text-2xl font-extrabold text-slate-900 font-['Outfit']">7 / 8 Kamar</h4>
                            <span class="text-[11px] text-teal-600 font-semibold">Tingkat Okupansi 87.5%</span>
                        </div>
                        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-2">
                            <span class="text-xs text-slate-500 font-bold uppercase tracking-wider block">Total Listing Kos</span>
                            <h4 class="text-2xl font-extrabold text-slate-900 font-['Outfit']" x-text="ownerProperties.length + ' Properti'"></h4>
                            <span class="text-[11px] text-slate-400">Aktif di Katalog EasyKos</span>
                        </div>
                    </div>

                    <div class="bg-gradient-to-r from-[#D95B3B] to-[#C84628] p-6 rounded-3xl text-white shadow-lg flex items-center justify-between flex-wrap gap-4">
                        <div class="space-y-1">
                            <h4 class="text-xl font-bold font-['Outfit']">Boost Your Listing!</h4>
                            <p class="text-xs text-rose-100">Dapatkan hingga 3x lipat calon penyewa dengan merawat Pet Moni dan mempromosikan kos di halaman depan.</p>
                        </div>
                        <button @click="ownerTab = 'pet'; loadPet();" class="bg-white text-[#D95B3B] px-5 py-2.5 rounded-full text-xs font-bold hover:bg-rose-50 transition">
                            Rawat Pet Sekarang
                        </button>
                    </div>
                </div>

                <!-- OWNER PROPERTIES TAB -->
                <div x-show="ownerTab === 'properties'" class="space-y-6">
                    <h3 class="text-2xl font-bold text-slate-900 font-['Outfit']">Daftar Properti Kos Saya</h3>
                    <div class="grid sm:grid-cols-2 gap-6">
                        <template x-for="prop in ownerProperties" :key="prop.id">
                            <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-xs p-5 space-y-4">
                                <img :src="prop.image" class="w-full h-44 object-cover rounded-2xl">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-md bg-teal-50 text-teal-700" x-text="prop.type"></span>
                                        <button @click="togglePropertyStatus(prop)" 
                                                :class="prop.status === 'Available' ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-rose-100 text-rose-700 hover:bg-rose-200'" 
                                                class="text-[11px] font-bold px-2.5 py-0.5 rounded-full transition cursor-pointer" 
                                                x-text="prop.status + ' (Klik Ubah)'"></button>
                                    </div>
                                    <h4 class="text-lg font-bold text-slate-900 font-['Outfit']" x-text="prop.name"></h4>
                                    <p class="text-xs text-slate-500" x-text="prop.address"></p>
                                </div>
                                <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                                    <span class="text-base font-extrabold text-[#0D9488]">Rp <span x-text="prop.price.toLocaleString('id-ID')"></span>/bln</span>
                                    <button @click="triggerToast('Kos telah sinkron dengan database')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-xs font-bold transition">Tersimpan</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- OWNER ADD LISTING TAB -->
                <div x-show="ownerTab === 'add_listing'" class="bg-white p-8 rounded-3xl border border-teal-100 space-y-6 shadow-sm">
                    <h3 class="text-2xl font-bold text-slate-900 font-['Outfit']">Tambah Listing Kos Baru (Simpan ke Database)</h3>
                    <div class="grid sm:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-700">Nama Kos</label>
                            <input type="text" x-model="newListing.name" placeholder="contoh: Kos Mawar Indah" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-teal-600">
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-700">Tipe Kamar</label>
                            <select x-model="newListing.type" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-teal-600 bg-white">
                                <option value="Standard Single">Standard Single</option>
                                <option value="Deluxe AC">Deluxe AC</option>
                                <option value="Premium Suite">Premium Suite</option>
                                <option value="Double Bed">Double Bed</option>
                            </select>
                        </div>

                        <div class="sm:col-span-2 space-y-2">
                            <label class="text-xs font-bold text-slate-700">Alamat Lengkap</label>
                            <input type="text" x-model="newListing.address" placeholder="Jl. Gejayan No. 12, Sleman, Yogyakarta" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-teal-600">
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-700">Harga Sewa per Bulan (Rp)</label>
                            <input type="number" x-model="newListing.price" placeholder="1500000" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-teal-600">
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-700">Upload Foto Kamar</label>
                            <div class="border-2 border-dashed border-teal-200 rounded-xl p-4 text-center bg-teal-50/40 cursor-pointer hover:bg-teal-50 transition">
                                <i class="fa-solid fa-cloud-arrow-up text-2xl text-teal-600 mb-1"></i>
                                <span class="block text-xs font-semibold text-slate-600">Foto Siap Diunggah</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button @click="ownerTab = 'properties'" class="px-6 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-xs font-bold hover:bg-slate-50 transition">Batal</button>
                        <button @click="saveNewListing()" class="bg-[#0D9488] hover:bg-[#0F766E] text-white px-8 py-2.5 rounded-xl text-xs font-bold shadow-md transition">Simpan ke Database</button>
                    </div>
                </div>

                <!-- OWNER REAL CHAT TAB -->
                <div x-show="ownerTab === 'messages'" class="bg-white rounded-3xl border border-teal-100 overflow-hidden shadow-sm grid lg:grid-cols-12 min-h-[550px]">
                    <div class="lg:col-span-4 border-r border-slate-100 p-4 space-y-3 bg-slate-50/50">
                        <div class="flex items-center justify-between px-2">
                            <h4 class="font-bold text-slate-900 text-sm font-['Outfit']">Calon Penyewa</h4>
                            <span class="inline-flex items-center gap-1 text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Live Auto-Sync
                            </span>
                        </div>
                        <div class="space-y-1">
                            <template x-for="contact in chatContacts" :key="contact.id">
                                <div @click="selectContact(contact.id)" 
                                     :class="activeContactId === contact.id ? 'bg-white border-teal-600 shadow-xs' : 'border-transparent hover:bg-white/80'"
                                     class="p-3 rounded-2xl border flex items-center gap-3 cursor-pointer transition">
                                    <div class="relative">
                                        <img :src="contact.avatar" class="w-11 h-11 rounded-full object-cover">
                                        <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-500 border-2 border-white"></span>
                                    </div>
                                    <div class="overflow-hidden flex-1">
                                        <div class="flex items-center justify-between">
                                            <h5 class="text-xs font-bold text-slate-900" x-text="contact.name"></h5>
                                            <span class="text-[10px] text-slate-400" x-text="contact.last_time"></span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 truncate" x-text="contact.last_message"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="lg:col-span-8 flex flex-col justify-between h-full bg-white">
                        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/30">
                            <div class="flex items-center gap-3">
                                <img :src="chatContacts.find(c => c.id === activeContactId)?.avatar || 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80'" class="w-10 h-10 rounded-full object-cover">
                                <div>
                                    <h5 class="text-xs font-bold text-slate-900" x-text="chatContacts.find(c => c.id === activeContactId)?.name || 'Pilih Calon Penyewa'"></h5>
                                    <span class="text-[10px] text-teal-600 font-semibold flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span> Real-Time Connected
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div id="ownerChatMessagesContainer" class="p-6 space-y-4 overflow-y-auto max-h-[380px] scroll-smooth">
                            <template x-for="msg in currentMessages" :key="msg.id">
                                <div class="flex flex-col" :class="msg.sender === 'me' ? 'items-end' : 'items-start'">
                                    <div class="max-w-xs sm:max-w-md px-4 py-3 rounded-2xl text-xs leading-relaxed shadow-xs"
                                         :class="msg.sender === 'me' ? 'bg-teal-600 text-white rounded-br-none' : 'bg-slate-100 text-slate-800 rounded-bl-none border border-slate-200'">
                                        <p x-text="msg.text"></p>
                                    </div>
                                    <span class="text-[9px] text-slate-400 mt-1 px-1" x-text="msg.time"></span>
                                </div>
                            </template>
                        </div>

                        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center gap-2">
                            <input type="text" x-model="chatInput" @keydown.enter="sendChatMessage()" placeholder="Ketik balasan untuk calon penyewa..." class="flex-1 bg-white border border-slate-200 rounded-full px-4 py-2.5 text-xs focus:outline-none focus:border-teal-600">
                            <button @click="sendChatMessage()" class="bg-teal-600 hover:bg-teal-700 text-white w-9 h-9 rounded-full flex items-center justify-center text-xs transition">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- OWNER VIRTUAL PET GAME TAB -->
                <div x-show="ownerTab === 'pet'" class="space-y-6">
                    <div class="bg-gradient-to-br from-teal-600 via-emerald-600 to-[#0D9488] p-8 rounded-3xl text-white shadow-xl relative overflow-hidden">
                        <div class="grid md:grid-cols-12 gap-8 items-center relative z-10">
                            
                            <div class="md:col-span-5 text-center">
                                <div class="relative w-56 h-56 mx-auto bg-white/20 backdrop-blur-md rounded-3xl p-4 border-2 border-white/40 shadow-2xl flex items-center justify-center">
                                    <div class="w-full h-full bg-white/10 rounded-2xl flex flex-col items-center justify-center p-2 text-white shadow-inner relative" :class="pet.mood === 'excited' ? 'animate-pet-happy' : 'animate-pet-bob'">
                                        <img :src="petImage" class="w-36 h-36 object-contain filter drop-shadow-2xl">
                                        <span class="text-xs font-bold mt-1 font-['Outfit']">Pet <span x-text="pet.name"></span> (Owner Pet)</span>
                                        <span class="text-[10px] bg-white/25 px-2.5 py-0.5 rounded-full font-bold" x-text="pet.hunger < 25 ? '🥜 Kelaparan Menangis' : (pet.health < 40 ? '😴 Sakit / Lemas' : (pet.happiness < 30 ? '🥺 Sedih / Bosan' : (pet.mood === 'excited' ? '✨ Sangat Gembira!' : '😊 Sehat & Ceria')))"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="md:col-span-7 space-y-3.5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-xs text-teal-100 font-bold uppercase tracking-wider flex items-center gap-1.5">
                                            <i class="fa-solid fa-clock-rotate-left"></i> Real Time-Engine Database
                                        </span>
                                        <h3 class="text-3xl font-extrabold font-['Outfit']">Pet <span x-text="pet.name"></span></h3>
                                    </div>
                                    <div class="bg-white/20 backdrop-blur-md px-4 py-2 rounded-2xl border border-white/30 text-right">
                                        <span class="text-[10px] block text-teal-100">Level Pet</span>
                                        <span class="text-2xl font-extrabold font-['Outfit']" x-text="pet.level"></span>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <div class="flex justify-between text-xs font-bold text-teal-100">
                                        <span><i class="fa-solid fa-star text-amber-300 mr-1"></i> EXP Progress Level</span>
                                        <span><span x-text="pet.exp"></span> / <span x-text="pet.max_exp"></span></span>
                                    </div>
                                    <div class="w-full h-3 bg-black/20 rounded-full overflow-hidden p-0.5">
                                        <div class="h-full bg-emerald-300 rounded-full transition-all duration-500 shadow-sm" :style="'width: ' + (pet.exp/pet.max_exp*100) + '%'"></div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3 pt-1">
                                    <div class="bg-black/10 p-3 rounded-2xl border border-white/10 space-y-1">
                                        <div class="flex justify-between text-[11px] font-bold text-teal-100">
                                            <span><i class="fa-solid fa-heart text-rose-300 mr-1"></i> Kesehatan</span>
                                            <span x-text="pet.health + '%'"></span>
                                        </div>
                                        <div class="w-full h-2 bg-black/20 rounded-full overflow-hidden">
                                            <div class="h-full bg-rose-400 rounded-full transition-all duration-300" :style="'width: ' + pet.health + '%'"></div>
                                        </div>
                                        <span class="text-[9px] text-teal-100/80 block">Berkurang jika lapar > 8 jam</span>
                                    </div>

                                    <div class="bg-black/10 p-3 rounded-2xl border border-white/10 space-y-1">
                                        <div class="flex justify-between text-[11px] font-bold text-teal-100">
                                            <span><i class="fa-solid fa-drumstick-bite text-orange-300 mr-1"></i> Kekenyangan</span>
                                            <span x-text="pet.hunger + '%'"></span>
                                        </div>
                                        <div class="w-full h-2 bg-black/20 rounded-full overflow-hidden">
                                            <div class="h-full bg-emerald-300 rounded-full transition-all duration-300" :style="'width: ' + pet.hunger + '%'"></div>
                                        </div>
                                        <span class="text-[9px] text-teal-100/80 block">Makan dlm: <span x-text="petFeedCountdown"></span></span>
                                    </div>
                                </div>

                                <div class="bg-black/10 p-3 rounded-2xl border border-white/10 space-y-1">
                                    <div class="flex justify-between text-[11px] font-bold text-teal-100">
                                        <span><i class="fa-solid fa-face-smile-beam text-yellow-300 mr-1"></i> Tingkat Kebahagiaan</span>
                                        <span x-text="pet.happiness + '%'"></span>
                                    </div>
                                    <div class="w-full h-2 bg-black/20 rounded-full overflow-hidden">
                                        <div class="h-full bg-yellow-400 rounded-full transition-all duration-300" :style="'width: ' + pet.happiness + '%'"></div>
                                    </div>
                                    <span class="text-[9px] text-teal-100/80 block">Bertahan selama 5 hari (120 jam)</span>
                                </div>

                                <div class="flex items-center gap-3 pt-1">
                                    <button @click="feedPet()" class="flex-1 py-3 bg-white text-slate-900 hover:bg-teal-50 font-bold rounded-2xl text-xs shadow-lg transition flex items-center justify-center gap-2">
                                        <i class="fa-solid fa-apple-whole text-emerald-600 text-sm"></i> Beri Makan (+40% Kenyangan)
                                    </button>
                                    <button @click="playPet()" class="flex-1 py-3 bg-slate-900 text-white hover:bg-slate-800 font-bold rounded-2xl text-xs shadow-lg transition flex items-center justify-center gap-2">
                                        <i class="fa-solid fa-futbol text-amber-400 text-sm"></i> Ajak Bermain (+35% Bahagia)
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- OWNER PAYOUTS TAB -->
                <div x-show="ownerTab === 'payouts'" class="bg-white p-8 rounded-3xl border border-teal-100 space-y-6 shadow-sm">
                    <h3 class="text-2xl font-bold text-slate-900 font-['Outfit']">Laporan Transaksi & Keuangan Masuk</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider">
                                <tr>
                                    <th class="p-3">Nama Penyewa</th>
                                    <th class="p-3">Kamar Kos</th>
                                    <th class="p-3">Jumlah</th>
                                    <th class="p-3">Metode</th>
                                    <th class="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <template x-for="b in myBookings" :key="b.id">
                                    <tr>
                                        <td class="p-3 font-semibold" x-text="b.user ? b.user.name : 'Penyewa'"></td>
                                        <td class="p-3" x-text="b.property ? b.property.name : 'Kos'"></td>
                                        <td class="p-3 font-bold text-slate-900">Rp <span x-text="b.total_price.toLocaleString('id-ID')"></span></td>
                                        <td class="p-3 uppercase font-semibold text-slate-600" x-text="b.payment_method"></td>
                                        <td class="p-3"><span class="bg-emerald-100 text-emerald-700 px-2.5 py-0.5 rounded-full font-bold" x-text="b.status"></span></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>


    <!-- ========================================== -->
    <!-- 4. AUTH MODALS                             -->
    <!-- ========================================== -->
    <div x-show="authModal !== 'none'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div @click.away="authModal = 'none'" class="bg-white max-w-md w-full rounded-3xl p-8 shadow-2xl border border-rose-100 space-y-6 relative">
            
            <button @click="authModal = 'none'" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <!-- LOGIN FORM -->
            <div x-show="authModal === 'login'" class="space-y-5">
                <div class="text-center space-y-2">
                    <img src="/images/logo-full.png" alt="EasyKos" class="h-10 mx-auto object-contain">
                    <h3 class="text-xl font-extrabold text-slate-900 font-['Outfit']">Login to EasyKos</h3>
                    <p class="text-slate-500 text-xs">Pilih akun riil di database untuk masuk instan</p>
                </div>

                <!-- Database Seed Accounts Quick Picker -->
                <div class="p-3.5 rounded-2xl bg-orange-50/80 border border-orange-200 space-y-2 text-xs">
                    <span class="font-bold text-[#D95B3B] block text-[11px] uppercase tracking-wider"><i class="fa-solid fa-users mr-1"></i> Akun Database Siap Pakai:</span>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="handleLogin('budi@easykos.com', 'password')" class="py-2 px-2.5 bg-white hover:bg-orange-100 border border-orange-300 rounded-xl text-[11px] font-bold text-slate-800 text-left transition shadow-2xs">
                            🧑‍🎓 Budi (Tenant)
                        </button>
                        <button type="button" @click="handleLogin('siti@easykos.com', 'password')" class="py-2 px-2.5 bg-white hover:bg-orange-100 border border-orange-300 rounded-xl text-[11px] font-bold text-slate-800 text-left transition shadow-2xs">
                            👩‍🎓 Siti (Tenant)
                        </button>
                        <button type="button" @click="handleLogin('supri@easykos.com', 'password')" class="py-2 px-2.5 bg-white hover:bg-teal-100 border border-teal-300 rounded-xl text-[11px] font-bold text-slate-800 text-left transition shadow-2xs">
                            🏢 Pak Supri (Owner)
                        </button>
                        <button type="button" @click="handleLogin('ratna@easykos.com', 'password')" class="py-2 px-2.5 bg-white hover:bg-teal-100 border border-teal-300 rounded-xl text-[11px] font-bold text-slate-800 text-left transition shadow-2xs">
                            🏢 Ibu Ratna (Owner)
                        </button>
                    </div>
                </div>

                <form @submit.prevent="handleLogin()" class="space-y-4">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700">Email Address</label>
                        <input type="email" x-model="loginEmail" placeholder="budi@easykos.com" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-[#D95B3B]">
                    </div>

                    <div class="space-y-1">
                        <div class="flex justify-between items-center">
                            <label class="text-xs font-bold text-slate-700">Password</label>
                            <a href="#" class="text-[11px] text-[#D95B3B] font-semibold hover:underline">Lupa Password?</a>
                        </div>
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'" x-model="loginPassword" placeholder="password" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-[#D95B3B] pr-10">
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-3 text-slate-400 hover:text-slate-600 text-sm">
                                <i :class="showPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="terracotta-gradient-btn text-white w-full py-3.5 rounded-2xl font-bold text-sm shadow-md">
                        Log In
                    </button>
                </form>

                <p class="text-center text-xs text-slate-500">
                    Belum punya akun? <button @click="authModal = 'role_choice'" class="text-[#D95B3B] font-bold hover:underline">Daftar Sekarang</button>
                </p>
            </div>

            <!-- ROLE CHOICE MODAL -->
            <div x-show="authModal === 'role_choice'" class="space-y-6">
                <div class="text-center space-y-2">
                    <img src="/images/logo-full.png" alt="EasyKos" class="h-10 mx-auto object-contain">
                    <h3 class="text-xl font-extrabold text-slate-900 font-['Outfit']">Pilih Peran Akun</h3>
                    <p class="text-slate-500 text-xs">Pilih kategori akun yang sesuai</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div @click="signupRole = 'tenant'; authModal = 'signup_tenant';" class="p-6 rounded-2xl border-2 border-slate-200 hover:border-[#D95B3B] hover:bg-orange-50/50 cursor-pointer text-center space-y-3 transition group">
                        <div class="w-14 h-14 mx-auto rounded-2xl bg-orange-100 text-[#D95B3B] flex items-center justify-center text-2xl group-hover:scale-110 transition">
                            <i class="fa-solid fa-magnifying-glass-location"></i>
                        </div>
                        <h4 class="font-bold text-slate-900 text-sm font-['Outfit']">Cari Kos</h4>
                        <span class="text-[11px] text-slate-500 block">Pencari / Mahasiswa</span>
                    </div>

                    <div @click="signupRole = 'owner'; authModal = 'signup_owner';" class="p-6 rounded-2xl border-2 border-slate-200 hover:border-[#0D9488] hover:bg-teal-50/50 cursor-pointer text-center space-y-3 transition group">
                        <div class="w-14 h-14 mx-auto rounded-2xl bg-teal-100 text-[#0D9488] flex items-center justify-center text-2xl group-hover:scale-110 transition">
                            <i class="fa-solid fa-building-user"></i>
                        </div>
                        <h4 class="font-bold text-slate-900 text-sm font-['Outfit']">Pemilik Kos</h4>
                        <span class="text-[11px] text-slate-500 block">Pemilik Properti</span>
                    </div>
                </div>

                <p class="text-center text-xs text-slate-500">
                    Sudah punya akun? <button @click="authModal = 'login'" class="text-[#D95B3B] font-bold hover:underline">Log In</button>
                </p>
            </div>

            <!-- SIGN UP FORM -->
            <div x-show="authModal === 'signup_tenant' || authModal === 'signup_owner'" class="space-y-5">
                <div class="text-center space-y-2">
                    <img src="/images/logo-full.png" alt="EasyKos" class="h-10 mx-auto object-contain">
                    <h3 class="text-xl font-extrabold text-slate-900 font-['Outfit']">Sign Up EasyKos</h3>
                    <span class="inline-block text-[11px] font-bold px-3 py-0.5 rounded-full" :class="signupRole === 'tenant' ? 'bg-orange-100 text-[#D95B3B]' : 'bg-teal-100 text-teal-700'" x-text="signupRole === 'tenant' ? 'Pencari Kos' : 'Pemilik Kos'"></span>
                </div>

                <form @submit.prevent="handleRegister()" class="space-y-3 text-left">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700">Nama Lengkap</label>
                        <input type="text" x-model="signupName" placeholder="Nama Lengkap" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-[#D95B3B]">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700">No. HP / WhatsApp</label>
                        <input type="text" x-model="signupPhone" placeholder="08123456789" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-[#D95B3B]">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700">Email Address</label>
                        <input type="email" x-model="signupEmail" placeholder="user@gmail.com" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-[#D95B3B]">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700">Password</label>
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'" x-model="signupPassword" placeholder="Min. 8 karakter" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-[#D95B3B] pr-10">
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 text-sm">
                                <i :class="showPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="terracotta-gradient-btn text-white w-full py-3 rounded-2xl font-bold text-sm shadow-md mt-2">
                        Daftar Sekarang
                    </button>
                </form>
            </div>

        </div>
    </div>


    <!-- ========================================== -->
    <!-- 5. KOS DETAIL MODAL                        -->
    <!-- ========================================== -->
    <div x-show="showKosModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
        <div @click.away="showKosModal = false" class="bg-white max-w-2xl w-full rounded-3xl p-6 sm:p-8 shadow-2xl border border-rose-100 space-y-6 my-8">
            <template x-if="selectedKos">
                <div class="space-y-6">
                    <div class="space-y-3">
                        <div class="relative">
                            <img :src="selectedKos.gallery ? selectedKos.gallery[selectedKosGalleryIndex] : selectedKos.image" class="w-full h-64 object-cover rounded-2xl transition-all duration-300">
                            <button @click="showKosModal = false" class="absolute top-4 right-4 w-9 h-9 rounded-full bg-slate-900/80 text-white flex items-center justify-center">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div class="flex items-center gap-2 overflow-x-auto pb-1" x-show="selectedKos.gallery && selectedKos.gallery.length > 1">
                            <template x-for="(img, idx) in selectedKos.gallery" :key="idx">
                                <img :src="img" @click="selectedKosGalleryIndex = idx" :class="selectedKosGalleryIndex === idx ? 'border-2 border-[#D95B3B] scale-105' : 'opacity-70'" class="w-16 h-12 object-cover rounded-xl cursor-pointer transition">
                            </template>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <span class="text-xs font-bold text-[#D95B3B] bg-orange-50 px-3 py-1 rounded-md" x-text="selectedKos.type"></span>
                        <h3 class="text-2xl font-extrabold text-slate-900 font-['Outfit']" x-text="selectedKos.name"></h3>
                        <p class="text-slate-500 text-xs flex items-center gap-1.5">
                            <i class="fa-solid fa-location-dot text-[#D95B3B]"></i> <span x-text="selectedKos.address"></span>
                        </p>
                        <p class="text-slate-600 text-xs leading-relaxed pt-1" x-text="selectedKos.description"></p>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-[#D95B3B] text-white flex items-center justify-center font-bold">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div>
                                <span class="block text-xs font-bold text-slate-900" x-text="selectedKos.owner ? selectedKos.owner.name : 'Pemilik Kos'"></span>
                                <span class="text-[10px] text-slate-500">Pemilik Kos Terverifikasi</span>
                            </div>
                        </div>
                        <button @click="requireAuthAction(() => { showKosModal = false; viewMode = 'tenant'; tenantTab = 'messages'; selectContact(selectedKos.user_id); })" class="bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 px-4 py-2 rounded-xl text-xs font-bold transition">
                            Chat Owner
                        </button>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                        <div>
                            <span class="text-xs text-slate-400 block">Harga Sewa</span>
                            <span class="text-xl font-extrabold text-[#D95B3B] font-['Outfit']">Rp <span x-text="selectedKos.price.toLocaleString('id-ID')"></span></span> <span class="text-xs text-slate-400">/bln</span>
                        </div>
                        <button @click="openPayment()" class="terracotta-gradient-btn text-white px-8 py-3 rounded-2xl font-bold text-sm shadow-md">
                            Book Now
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>


    <!-- ========================================== -->
    <!-- 6. PAYMENT / BOOKING MODAL                 -->
    <!-- ========================================== -->
    <div x-show="showPaymentModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div @click.away="showPaymentModal = false" class="bg-white max-w-md w-full rounded-3xl p-6 sm:p-8 shadow-2xl border border-rose-100 space-y-5 relative">
            
            <button @click="showPaymentModal = false" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <div class="text-center space-y-1">
                <h3 class="text-2xl font-extrabold text-slate-900 font-['Outfit']">Pembayaran Sewa Kos</h3>
                <p class="text-slate-500 text-xs">Transaksi tercatat langsung di Database SQLite</p>
            </div>

            <div class="bg-slate-50 p-4 rounded-2xl space-y-2 border border-slate-100 text-xs">
                <div class="flex justify-between text-slate-600">
                    <span>Kamar Kos:</span>
                    <span class="font-bold text-slate-900" x-text="selectedKos ? selectedKos.name : ''"></span>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>Durasi Sewa:</span>
                    <span class="font-bold text-slate-900">1 Bulan</span>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>Harga Normal:</span>
                    <span x-text="selectedKos ? 'Rp ' + selectedKos.price.toLocaleString('id-ID') : ''"></span>
                </div>
                <div x-show="appliedDiscount > 0" class="flex justify-between text-emerald-600 font-bold">
                    <span>Diskon Pet Voucher:</span>
                    <span x-text="'- Rp ' + appliedDiscount.toLocaleString('id-ID')"></span>
                </div>
                <div class="flex justify-between text-slate-900 pt-2 border-t border-slate-200 font-bold text-sm">
                    <span>Total Pembayaran:</span>
                    <span class="text-base text-[#D95B3B]">Rp <span x-text="finalPaymentTotal.toLocaleString('id-ID')"></span></span>
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-700 flex items-center justify-between">
                    <span>Gunakan Voucher Pet:</span>
                    <span class="text-[10px] text-[#D95B3B]">Ketik: VETPET100</span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="text" x-model="voucherCodeInput" placeholder="Masukkan kode voucher" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-[#D95B3B] uppercase">
                    <button @click="applyVoucher()" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-800 transition">
                        Gunakan
                    </button>
                </div>
                <span x-show="appliedVoucherText" class="text-[11px] text-emerald-600 font-semibold block" x-text="appliedVoucherText"></span>
            </div>

            <div class="space-y-2">
                <label class="text-xs font-bold text-slate-700">Pilih Metode Pembayaran</label>
                <div class="grid grid-cols-2 gap-2.5">
                    <button @click="paymentMethod = 'ovo'" :class="paymentMethod === 'ovo' ? 'border-[#D95B3B] bg-orange-50/60 font-bold' : 'border-slate-200'" class="p-2.5 rounded-xl border text-xs text-left flex items-center gap-2">
                        <i class="fa-solid fa-wallet text-purple-600"></i> OVO
                    </button>
                    <button @click="paymentMethod = 'dana'" :class="paymentMethod === 'dana' ? 'border-[#D95B3B] bg-orange-50/60 font-bold' : 'border-slate-200'" class="p-2.5 rounded-xl border text-xs text-left flex items-center gap-2">
                        <i class="fa-solid fa-wallet text-blue-500"></i> DANA
                    </button>
                    <button @click="paymentMethod = 'qris'" :class="paymentMethod === 'qris' ? 'border-[#D95B3B] bg-orange-50/60 font-bold' : 'border-slate-200'" class="p-2.5 rounded-xl border text-xs text-left flex items-center gap-2">
                        <i class="fa-solid fa-qrcode text-red-500"></i> QRIS Instant
                    </button>
                    <button @click="paymentMethod = 'bca'" :class="paymentMethod === 'bca' ? 'border-[#D95B3B] bg-orange-50/60 font-bold' : 'border-slate-200'" class="p-2.5 rounded-xl border text-xs text-left flex items-center gap-2">
                        <i class="fa-solid fa-building-columns text-blue-800"></i> BCA VA
                    </button>
                </div>
            </div>

            <button @click="processPayment()" class="terracotta-gradient-btn text-white w-full py-3.5 rounded-2xl font-bold text-sm shadow-md">
                Bayar Sekarang
            </button>
        </div>
    </div>


    <!-- ========================================== -->
    <!-- 7. TOAST NOTIFICATION                      -->
    <!-- ========================================== -->
    <div x-show="showToast" 
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-y-8 opacity-0 scale-95"
         x-transition:enter-end="translate-y-0 opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-y-0 opacity-100 scale-100"
         x-transition:leave-end="translate-y-8 opacity-0 scale-95"
         class="fixed bottom-6 right-6 z-50 bg-slate-900/95 text-white px-5 py-3.5 rounded-2xl shadow-2xl border border-slate-700 flex items-center gap-3 text-xs font-semibold">
        <i class="fa-solid fa-sparkles text-amber-400 text-sm"></i>
        <span x-text="toastMsg"></span>
    </div>

</div>
@endsection

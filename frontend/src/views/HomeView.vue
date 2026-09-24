<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { productService, type Product } from '@/services/product.service'
import { formatPeso, productStatusClass } from '@/utils/formatters'

// ── Featured products ────────────────────────────────────────────────────────
const featured  = ref<Product[]>([])
const featLoading = ref(true)

onMounted(async () => {
  try {
    const res = await productService.list({ page: 1 })
    // Show first 6 products as featured
    featured.value = res.data.slice(0, 6)
  } finally {
    featLoading.value = false
  }
})

// ── Request form ─────────────────────────────────────────────────────────────
const activeTab   = ref<'seek' | 'ask'>('seek')
const requestForm = ref({ piece: '', category: '', details: '', message: '' })

function handleRequest() {
  // Placeholder — connect to backend endpoint when available
  alert('Hunt request received! We will reach out soon.')
  requestForm.value = { piece: '', category: '', details: '', message: '' }
}

// ── Back to top ───────────────────────────────────────────────────────────────
function backToTop() { window.scrollTo({ top: 0, behavior: 'smooth' }) }

// ── Rotations for rack items ──────────────────────────────────────────────────
const rotations = [-3, 2, -1.5, 3.5, -2.5, 1, -0.5, 2.8]
function getRot(i: number) { return rotations[i % rotations.length] }
</script>

<template>
  <!-- ── HERO ───────────────────────────────────────────────────────────── -->
  <section class="hero" aria-label="Hero section">
    <div class="hero__bg" aria-hidden="true">
      <!-- Textured dark overlay on top of a placeholder earthy background -->
      <div class="hero__overlay" />
      <div class="hero__grain" />
    </div>

    <div class="container hero__content">
      <div class="hero__text">
        <p class="hero__eyebrow">Est. Manila — Curated Vintage</p>
        <h1 class="hero__headline display">
          THE HUNT<br />IS ON
        </h1>
        <p class="hero__sub">
          Curated thrift and vintage pieces,<br />
          hunted from one destination to the next.
        </p>
        <div class="hero__btns">
          <RouterLink to="/catalog" class="btn hero__btn">
            Start Hunting
          </RouterLink>
          <RouterLink to="/" class="btn hero__btn hero__btn--outline">
            Where We Roam
          </RouterLink>
        </div>
      </div>

      <!-- decorative marks -->
      <div class="hero__deco" aria-hidden="true">
        <span class="hero__deco-circle hero__deco-circle--1" />
        <span class="hero__deco-circle hero__deco-circle--2" />
        <span class="hero__deco-label">/ Season 2026</span>
      </div>
    </div>
  </section>

  <!-- ── NEXT FIELD ANNOUNCEMENT ────────────────────────────────────────── -->
  <section class="announcement" aria-label="Upcoming event">
    <div class="announcement__inner container">
      <div class="announcement__left">
        <span class="announcement__label display">NEXT FIELD</span>
        <span class="announcement__event">September 26–27 &nbsp;|&nbsp; 11AM–9PM &nbsp;|&nbsp; G Studios, Alabang</span>
      </div>
      <div class="announcement__right">
        <span class="announcement__heading display">Alabang Booth</span>
      </div>
    </div>
    <div class="announcement__bg-rack" aria-hidden="true" />
  </section>

  <!-- ── IN THE WILD ─────────────────────────────────────────────────────── -->
  <section class="wild" aria-labelledby="wild-heading">
    <div class="container">
      <div class="wild__header">
        <h2 id="wild-heading" class="wild__title">IN THE WILD</h2>
        <RouterLink to="/catalog" class="wild__see-all">See Collections →</RouterLink>
      </div>
    </div>

    <!-- Rack of featured products -->
    <div class="wild__rack" role="list" aria-label="Featured products">
      <div
        v-if="featLoading"
        class="spinner"
        style="margin: 4rem auto"
      />

      <template v-else>
        <RouterLink
          v-for="(p, i) in featured"
          :key="p.id"
          :to="`/products/${p.id}`"
          class="wild__item"
          role="listitem"
          :aria-label="`${p.name} — ${formatPeso(p.mine_price)}`"
          :style="`--rot: ${getRot(i)}deg`"
        >
          <!-- Status indicator dot -->
          <span
            :class="['wild__item-dot', productStatusClass(p.status)]"
            :aria-label="p.status"
          />

          <!-- Image -->
          <div class="wild__item-img-wrap">
            <img
              v-if="p.image_url"
              :src="p.image_url"
              :alt="p.name"
              class="wild__item-img"
              loading="lazy"
            />
            <div v-else class="wild__item-img-placeholder" />
            <div class="wild__item-overlay" aria-hidden="true" />
          </div>

          <!-- Label -->
          <div class="wild__item-info" aria-hidden="true">
            <span class="wild__item-brand">{{ p.brand ?? '' }}</span>
            <span class="wild__item-name">{{ p.name }}</span>
            <span class="wild__item-price">{{ formatPeso(p.mine_price) }}</span>
          </div>
        </RouterLink>
      </template>
    </div>
  </section>

  <!-- ── TALK TO THE NOMADS ─────────────────────────────────────────────── -->
  <section class="talk" aria-labelledby="talk-heading">
    <div class="talk__dark" aria-hidden="true">
      <!-- Left dark panel — decorative editorial block -->
      <div class="talk__dark-content">
        <p class="talk__dark-eyebrow display">/ Archive</p>
        <h3 class="talk__dark-headline display">EVERY PIECE<br />HAS A STORY</h3>
        <p class="talk__dark-sub">
          We hunt across destinations, markets, and wardrobes.<br />
          Can't find what you're looking for? Tell us.
        </p>
        <div class="talk__dark-marks" aria-hidden="true">
          <span class="talk__dark-mark" />
          <span class="talk__dark-mark talk__dark-mark--2" />
        </div>
      </div>
    </div>

    <div class="talk__light">
      <div class="talk__form-wrap">
        <h2 id="talk-heading" class="talk__form-title display">TALK TO THE NOMADS</h2>

        <!-- Tabs -->
        <div class="talk__tabs" role="tablist" aria-label="Request type">
          <button
            role="tab"
            :aria-selected="activeTab === 'seek'"
            :class="['talk__tab', { 'talk__tab--active': activeTab === 'seek' }]"
            @click="activeTab = 'seek'"
          >Seek</button>
          <button
            role="tab"
            :aria-selected="activeTab === 'ask'"
            :class="['talk__tab', { 'talk__tab--active': activeTab === 'ask' }]"
            @click="activeTab = 'ask'"
          >Ask</button>
        </div>

        <form class="talk__form" @submit.prevent="handleRequest" aria-label="Hunt request form">
          <div class="form-group">
            <label for="req-piece" class="talk__label">Piece</label>
            <input
              id="req-piece"
              v-model="requestForm.piece"
              type="text"
              class="talk__input"
              :placeholder="activeTab === 'seek' ? 'Leather jacket, varsity, tee…' : 'What would you like to know?'"
              required
            />
          </div>

          <div class="form-group">
            <label for="req-category" class="talk__label">Category</label>
            <input
              id="req-category"
              v-model="requestForm.category"
              type="text"
              class="talk__input"
              placeholder="Outerwear, Tops, Bottoms…"
            />
          </div>

          <div class="form-group">
            <label for="req-details" class="talk__label">Size / Era / Brand</label>
            <input
              id="req-details"
              v-model="requestForm.details"
              type="text"
              class="talk__input"
              placeholder="e.g. XL, 90s, Levi's, deadstock…"
            />
          </div>

          <div class="form-group">
            <label for="req-message" class="talk__label">Tell us more</label>
            <textarea
              id="req-message"
              v-model="requestForm.message"
              class="talk__textarea"
              rows="4"
              placeholder="Describe the piece, occasion, budget…"
            />
          </div>

          <button type="submit" class="talk__submit">
            Send Hunt Request →
          </button>
        </form>
      </div>
    </div>
  </section>

  <!-- ── FOOTER ──────────────────────────────────────────────────────────── -->
  <footer class="footer" role="contentinfo">
    <div class="footer__inner container">
      <!-- Brand -->
      <div class="footer__brand">
        <RouterLink to="/" class="footer__logo display" aria-label="NOMADS.HUNT Home">
          NOMADS<span>.</span>HUNT
        </RouterLink>
        <p class="footer__tagline">
          Vintage &amp; thrift pieces hunted<br />from one destination to the next.
        </p>
      </div>

      <!-- Customer Service -->
      <div class="footer__col">
        <h3 class="footer__col-title">Customer Service</h3>
        <nav aria-label="Customer service links">
          <RouterLink to="/" class="footer__link">Contact Us</RouterLink>
          <RouterLink to="/" class="footer__link">FAQs</RouterLink>
          <RouterLink to="/" class="footer__link">Return &amp; Refund</RouterLink>
        </nav>
      </div>

      <!-- Company -->
      <div class="footer__col">
        <h3 class="footer__col-title">Company</h3>
        <nav aria-label="Company links">
          <RouterLink to="/" class="footer__link">About Us</RouterLink>
          <RouterLink to="/" class="footer__link">Terms &amp; Conditions</RouterLink>
          <RouterLink to="/" class="footer__link">Privacy Policy</RouterLink>
        </nav>
      </div>

      <!-- Follow Us -->
      <div class="footer__col">
        <h3 class="footer__col-title">Follow Us</h3>
        <div class="footer__socials" aria-label="Social media links">
          <!-- Only show if accounts exist — placeholder SVG icons, no invented links -->
          <a href="#" class="footer__social-link" aria-label="Instagram">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
              <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
              <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
            </svg>
          </a>
          <a href="#" class="footer__social-link" aria-label="TikTok">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
            </svg>
          </a>
          <a href="#" class="footer__social-link" aria-label="Facebook">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
            </svg>
          </a>
        </div>
      </div>
    </div>

    <!-- Footer bottom bar -->
    <div class="footer__bar container">
      <p class="footer__copy">© 2026 NOMADS.HUNT. All rights reserved.</p>
      <p class="footer__made">Hunt responsibly.</p>
    </div>

    <!-- Back to top -->
    <button class="footer__back-to-top" aria-label="Back to top" @click="backToTop">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="19" x2="12" y2="5"/>
        <polyline points="5 12 12 5 19 12"/>
      </svg>
    </button>
  </footer>
</template>

<style scoped>
/* ── HERO ──────────────────────────────────────────────────────────────────── */
.hero {
  position: relative;
  min-height: 92vh;
  display: flex;
  align-items: center;
  overflow: hidden;
  background: var(--color-balsamico);
  padding-top: var(--nav-height);
}

.hero__bg {
  position: absolute;
  inset: 0;
  background-image:
    url('https://images.unsplash.com/photo-1558171813-3a46a5f7a25d?w=1800&q=80&auto=format&fit=crop');
  background-size: cover;
  background-position: center 30%;
}
.hero__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    105deg,
    rgba(26,15,5,.92) 0%,
    rgba(26,15,5,.78) 50%,
    rgba(26,15,5,.55) 100%
  );
}
.hero__grain {
  position: absolute;
  inset: 0;
  opacity: .04;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
  background-size: 200px;
}

.hero__content {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 4rem;
  padding-bottom: 4rem;
  gap: 3rem;
}

.hero__text {
  max-width: 580px;
}

.hero__eyebrow {
  font-size: .75rem;
  font-weight: 600;
  letter-spacing: .2em;
  text-transform: uppercase;
  color: var(--color-spice-market);
  margin-bottom: 1.25rem;
}

.hero__headline {
  font-size: clamp(3.5rem, 8vw, 7rem);
  line-height: .95;
  color: var(--color-spice-market);
  margin-bottom: 1.75rem;
  letter-spacing: -.01em;
}

.hero__sub {
  font-size: 1.05rem;
  line-height: 1.7;
  color: var(--color-seashell-strong);
  margin-bottom: 2.5rem;
  max-width: 400px;
}

.hero__btns {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.hero__btn {
  background: transparent;
  border: 1px solid var(--color-spice-market);
  color: var(--color-spice-market);
  font-size: .8rem;
  letter-spacing: .14em;
  padding: .75rem 2rem;
  transition: all var(--transition-fast);
}
.hero__btn:hover {
  background: var(--color-spice-market);
  color: var(--color-seashell);
}
.hero__btn--outline {
  border-color: rgba(254,243,238,.2);
  color: var(--color-seashell-muted);
}
.hero__btn--outline:hover {
  background: rgba(254,243,238,.08);
  border-color: rgba(254,243,238,.4);
  color: var(--color-seashell);
}

/* Decorative elements */
.hero__deco {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 1rem;
  flex-shrink: 0;
}
.hero__deco-circle {
  display: block;
  border-radius: 50%;
  border: 1px solid rgba(186,68,29,.25);
}
.hero__deco-circle--1 { width: 80px; height: 80px; }
.hero__deco-circle--2 {
  width: 50px; height: 50px;
  border-color: rgba(254,243,238,.1);
}
.hero__deco-label {
  font-size: .65rem;
  letter-spacing: .18em;
  text-transform: uppercase;
  color: rgba(254,243,238,.25);
  writing-mode: vertical-rl;
  transform: rotate(180deg);
}

/* ── ANNOUNCEMENT ─────────────────────────────────────────────────────────── */
.announcement {
  position: relative;
  height: 140px;
  overflow: hidden;
  border-top: 1px solid var(--color-balsamico-border);
  border-bottom: 1px solid var(--color-balsamico-border);
}

.announcement__bg-rack {
  position: absolute;
  inset: 0;
  background: url('https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=1400&q=60&auto=format&fit=crop')
    center center / cover no-repeat;
  opacity: .18;
}

.announcement__inner {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 100%;
  gap: 2rem;
}

.announcement__left {
  display: flex;
  flex-direction: column;
  gap: .5rem;
}

.announcement__label {
  font-size: 1.1rem;
  letter-spacing: .12em;
  color: var(--color-seashell);
  background: var(--color-spice-market);
  padding: .2rem .75rem;
  display: inline-block;
  line-height: 1.4;
}

.announcement__event {
  font-size: .8rem;
  font-weight: 600;
  letter-spacing: .08em;
  color: var(--color-seashell-muted);
  text-transform: uppercase;
}

.announcement__right {}
.announcement__heading {
  font-size: clamp(1.8rem, 4vw, 3rem);
  color: var(--color-spice-market);
  letter-spacing: .04em;
  white-space: nowrap;
}

/* ── IN THE WILD ───────────────────────────────────────────────────────────── */
.wild {
  padding: 6rem 0 5rem;
  background: var(--color-balsamico);
  overflow: hidden;
}

.wild__header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: 3rem;
}

.wild__title {
  font-family: var(--font-body);
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: .2em;
  text-transform: uppercase;
  color: var(--color-seashell-muted);
}

.wild__see-all {
  font-size: .75rem;
  font-weight: 600;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--color-spice-market);
  transition: opacity var(--transition-fast);
}
.wild__see-all:hover { opacity: .7; }

/* ── Rack layout ──────────────────────────────────────────────────────────── */
.wild__rack {
  display: flex;
  gap: 0;
  padding: 0 2rem 2rem;
  overflow-x: auto;
  scrollbar-width: thin;
  align-items: flex-end;
  min-height: 480px;
  -webkit-overflow-scrolling: touch;
}
.wild__rack::-webkit-scrollbar { height: 3px; }
.wild__rack::-webkit-scrollbar-track { background: transparent; }
.wild__rack::-webkit-scrollbar-thumb { background: var(--color-balsamico-border); }

.wild__item {
  position: relative;
  flex-shrink: 0;
  width: 220px;
  height: 360px;
  cursor: pointer;
  transform: rotate(var(--rot, 0deg));
  transition: transform var(--transition-normal), z-index 0s;
  margin-right: -18px;  /* intentional overlap */
  transform-origin: bottom center;
  text-decoration: none;
  display: block;
}
.wild__item:hover {
  transform: rotate(0deg) translateY(-12px);
  z-index: 10;
}

.wild__item-dot {
  position: absolute;
  top: .75rem;
  left: .75rem;
  z-index: 3;
  width: 10px; height: 10px;
  border-radius: 50%;
  background: var(--color-seashell-muted);
  border: 1px solid rgba(254,243,238,.2);
}
.wild__item-dot.badge--mine,
.wild__item-dot.badge--steal,
.wild__item-dot.badge--active {
  background: var(--color-spice-market);
  border-color: var(--color-spice-market);
}
.wild__item-dot.badge--sold {
  background: rgba(254,243,238,.2);
}

.wild__item-img-wrap {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
  border: 1px solid var(--color-balsamico-border);
}

.wild__item-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform var(--transition-normal);
}
.wild__item:hover .wild__item-img {
  transform: scale(1.04);
}

.wild__item-img-placeholder {
  width: 100%;
  height: 100%;
  background: var(--color-balsamico-lighter);
}

.wild__item-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to top,
    rgba(26,15,5,.88) 0%,
    rgba(26,15,5,.3) 40%,
    transparent 70%
  );
  transition: opacity var(--transition-normal);
}
.wild__item:hover .wild__item-overlay {
  opacity: .65;
}

.wild__item-info {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  padding: 1rem .875rem;
  z-index: 2;
  display: flex;
  flex-direction: column;
  gap: .2rem;
}

.wild__item-brand {
  font-size: .62rem;
  font-weight: 700;
  letter-spacing: .15em;
  text-transform: uppercase;
  color: var(--color-seashell-muted);
}

.wild__item-name {
  font-size: .85rem;
  font-weight: 600;
  color: var(--color-seashell);
  line-height: 1.3;
  /* Vertical label treatment */
  writing-mode: vertical-lr;
  text-orientation: mixed;
  transform: rotate(180deg);
  height: 130px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wild__item-price {
  font-size: .85rem;
  font-weight: 700;
  color: var(--color-spice-market);
  margin-top: .5rem;
  writing-mode: horizontal-tb;
  transform: none;
}

/* ── TALK TO THE NOMADS ───────────────────────────────────────────────────── */
.talk {
  display: grid;
  grid-template-columns: 55fr 45fr;
  min-height: 680px;
  border-top: 1px solid var(--color-balsamico-border);
}

.talk__dark {
  background: var(--color-balsamico);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding: 5rem 4rem 5rem 2rem;
  position: relative;
  overflow: hidden;
  border-right: 1px solid var(--color-balsamico-border);
}

.talk__dark-content {
  max-width: 440px;
  position: relative;
  z-index: 1;
}

.talk__dark-eyebrow {
  font-size: .7rem;
  letter-spacing: .25em;
  color: var(--color-spice-market);
  margin-bottom: 1.5rem;
  display: block;
}

.talk__dark-headline {
  font-size: clamp(2rem, 4vw, 3.5rem);
  color: var(--color-seashell);
  line-height: 1.0;
  margin-bottom: 1.5rem;
}

.talk__dark-sub {
  font-size: .9rem;
  line-height: 1.8;
  color: var(--color-seashell-muted);
  max-width: 320px;
}

.talk__dark-marks {
  margin-top: 3rem;
  display: flex;
  gap: 1rem;
  align-items: center;
}
.talk__dark-mark {
  display: block;
  width: 40px; height: 1px;
  background: var(--color-spice-market);
  opacity: .4;
}
.talk__dark-mark--2 {
  width: 20px;
  opacity: .2;
}

.talk__light {
  background: var(--color-seashell);
  display: flex;
  align-items: center;
  padding: 5rem 3.5rem;
}

.talk__form-wrap {
  width: 100%;
  max-width: 440px;
}

.talk__form-title {
  font-size: 1.4rem;
  color: var(--color-balsamico);
  margin-bottom: 1.75rem;
  letter-spacing: .04em;
  line-height: 1.15;
}

/* Tabs */
.talk__tabs {
  display: flex;
  gap: 0;
  margin-bottom: 2rem;
  border-bottom: 1px solid rgba(26,15,5,.15);
}

.talk__tab {
  background: transparent;
  border: none;
  font-family: var(--font-body);
  font-size: .8rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: rgba(26,15,5,.35);
  padding: .75rem 1.5rem .75rem 0;
  cursor: pointer;
  position: relative;
  transition: color var(--transition-fast);
}
.talk__tab::after {
  content: '';
  position: absolute;
  bottom: -1px; left: 0;
  width: 100%;
  height: 2px;
  background: var(--color-balsamico);
  transform: scaleX(0);
  transition: transform var(--transition-fast);
}
.talk__tab--active {
  color: var(--color-balsamico);
}
.talk__tab--active::after {
  transform: scaleX(1);
}

/* Form fields on seashell background */
.talk__label {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(26,15,5,.5);
  display: block;
  margin-bottom: .4rem;
}

.talk__input,
.talk__textarea {
  width: 100%;
  background: transparent;
  border: none;
  border-bottom: 1px solid rgba(26,15,5,.2);
  border-radius: 0;
  color: var(--color-balsamico);
  font-family: var(--font-body);
  font-size: .95rem;
  padding: .6rem 0;
  transition: border-color var(--transition-fast);
  outline: none;
}
.talk__input::placeholder,
.talk__textarea::placeholder {
  color: rgba(26,15,5,.25);
}
.talk__input:focus,
.talk__textarea:focus {
  border-bottom-color: var(--color-spice-market);
}
.talk__textarea { resize: vertical; min-height: 90px; }

.talk__form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.talk__submit {
  align-self: flex-end;
  background: transparent;
  border: none;
  font-family: var(--font-body);
  font-size: .8rem;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--color-spice-market);
  cursor: pointer;
  padding: .5rem 0;
  border-bottom: 1px solid var(--color-spice-market);
  transition: opacity var(--transition-fast);
}
.talk__submit:hover { opacity: .7; }

/* ── FOOTER ──────────────────────────────────────────────────────────────── */
.footer {
  background: var(--color-spice-market);
  color: var(--color-seashell);
  position: relative;
}

.footer__inner {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 3rem;
  padding-top: 4rem;
  padding-bottom: 3rem;
  border-bottom: 1px solid rgba(254,243,238,.15);
}

.footer__logo {
  font-size: 1.6rem;
  letter-spacing: .06em;
  color: var(--color-seashell);
  display: block;
  margin-bottom: 1rem;
  line-height: 1;
}
.footer__logo span { opacity: .6; }

.footer__tagline {
  font-size: .82rem;
  line-height: 1.75;
  color: rgba(254,243,238,.65);
}

.footer__col-title {
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .18em;
  text-transform: uppercase;
  color: rgba(254,243,238,.5);
  margin-bottom: 1.25rem;
}

.footer__col nav {
  display: flex;
  flex-direction: column;
  gap: .75rem;
}

.footer__link {
  font-size: .85rem;
  color: rgba(254,243,238,.75);
  transition: color var(--transition-fast);
}
.footer__link:hover { color: var(--color-seashell); }

.footer__socials {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.footer__social-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px; height: 36px;
  border: 1px solid rgba(254,243,238,.25);
  border-radius: 50%;
  color: rgba(254,243,238,.65);
  transition: all var(--transition-fast);
}
.footer__social-link:hover {
  background: rgba(254,243,238,.1);
  color: var(--color-seashell);
  border-color: rgba(254,243,238,.5);
}

.footer__bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 2rem;
}

.footer__copy,
.footer__made {
  font-size: .75rem;
  color: rgba(254,243,238,.4);
  letter-spacing: .06em;
}

.footer__back-to-top {
  position: absolute;
  bottom: 2.5rem;
  right: 2.5rem;
  width: 44px; height: 44px;
  background: rgba(254,243,238,.1);
  border: 1px solid rgba(254,243,238,.2);
  border-radius: 50%;
  color: rgba(254,243,238,.7);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all var(--transition-fast);
}
.footer__back-to-top:hover {
  background: rgba(254,243,238,.2);
  color: var(--color-seashell);
  border-color: rgba(254,243,238,.4);
}

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width: 1024px) {
  .talk {
    grid-template-columns: 1fr;
  }
  .talk__dark {
    padding: 4rem 2rem;
    border-right: none;
    border-bottom: 1px solid var(--color-balsamico-border);
    justify-content: flex-start;
  }
  .footer__inner {
    grid-template-columns: 1fr 1fr;
    gap: 2.5rem;
  }
}

@media (max-width: 768px) {
  .hero__content { flex-direction: column; align-items: flex-start; }
  .hero__deco    { display: none; }
  .hero__headline { font-size: clamp(3rem, 14vw, 4.5rem); }

  .announcement { height: auto; padding: 1.5rem 0; }
  .announcement__inner { flex-direction: column; align-items: flex-start; gap: 1rem; }
  .announcement__heading { font-size: 1.8rem; }

  .wild__rack { padding: 0 1rem 1.5rem; }
  .wild__item { width: 180px; height: 300px; }

  .talk__light { padding: 3rem 1.5rem; }

  .footer__inner { grid-template-columns: 1fr; gap: 2rem; padding: 3rem 0; }
  .footer__bar   { flex-direction: column; gap: .5rem; text-align: center; padding: 1rem 0; }
  .footer__back-to-top { bottom: 1.5rem; right: 1.5rem; }
}
</style>

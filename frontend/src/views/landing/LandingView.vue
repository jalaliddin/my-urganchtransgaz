<script setup lang="ts">
import '@fontsource-variable/onest/wght.css'
import '@fontsource/unbounded/latin-600.css'
import '@fontsource/unbounded/cyrillic-600.css'

import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import logoUrl from '@/assets/logo.svg'
import IssuesMiniMap from '@/components/landing/IssuesMiniMap.vue'
import NetworkMap from '@/components/landing/NetworkMap.vue'
import { setLocale, type SupportedLocale } from '@/plugins/i18n'
import { useAuthStore } from '@/stores/auth'

const { t, locale } = useI18n()
const auth = useAuthStore()

const languages: { code: SupportedLocale; label: string }[] = [
  { code: 'uz', label: "O'z" },
  { code: 'ru', label: 'Рус' },
  { code: 'en', label: 'Eng' },
]

const entryRoute = computed(() => (auth.isAuthenticated ? { name: 'dashboard' } : { name: 'login' }))
const entryLabel = computed(() => (auth.isAuthenticated ? t('landing.nav.dashboard') : t('landing.nav.login')))
const heroCta = computed(() => (auth.isAuthenticated ? t('landing.hero.ctaAuthed') : t('landing.hero.cta')))

// Two weeks of attendance as a decorative calendar strip: p present,
// l late, a absent, w weekend.
const attendanceCells = ['p', 'p', 'p', 'l', 'p', 'w', 'w', 'p', 'p', 'a', 'p', 'p', 'w', 'w']

const scrolled = ref(false)

function onScroll() {
  scrolled.value = window.scrollY > 12
}

function scrollTo(id: string) {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  document.getElementById(id)?.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' })
}

const previousTitle = document.title

function syncTitle() {
  document.title = t('landing.hero.title')
}

watch(locale, syncTitle)

onMounted(() => {
  syncTitle()
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
  document.title = previousTitle
})
</script>

<template>
  <div class="landing">
    <header class="bar" :class="{ 'bar--scrolled': scrolled }">
      <div class="wrap bar-inner">
        <router-link :to="{ name: 'landing' }" class="brand" :aria-label="t('app.name')">
          <img :src="logoUrl" alt="" class="brand-logo" />
        </router-link>

        <nav class="bar-nav" aria-label="Sections">
          <a href="#features" @click.prevent="scrollTo('features')">{{ t('landing.nav.features') }}</a>
          <a href="#mobile" @click.prevent="scrollTo('mobile')">{{ t('landing.nav.mobile') }}</a>
        </nav>

        <div class="bar-actions">
          <div class="lang" role="group" aria-label="Language">
            <button
              v-for="lang in languages"
              :key="lang.code"
              type="button"
              class="lang-btn"
              :aria-pressed="locale === lang.code"
              @click="setLocale(lang.code)"
            >
              {{ lang.label }}
            </button>
          </div>
          <router-link :to="entryRoute" class="btn btn--outline btn--sm">{{ entryLabel }}</router-link>
        </div>
      </div>
    </header>

    <main>
      <section class="hero wrap" aria-labelledby="hero-title">
        <div class="hero-copy">
          <h1 id="hero-title" class="hero-title">{{ t('landing.hero.title') }}</h1>
          <p class="hero-lead">{{ t('landing.hero.lead') }}</p>

          <div class="hero-actions">
            <router-link :to="entryRoute" class="btn btn--primary">{{ heroCta }}</router-link>
            <a href="#features" class="btn btn--ghost" @click.prevent="scrollTo('features')">
              {{ t('landing.hero.secondary') }}
              <span class="mdi mdi-arrow-down" aria-hidden="true" />
            </a>
          </div>

          <ul class="hero-points">
            <li><span class="mdi mdi-cellphone-link" aria-hidden="true" />{{ t('landing.hero.point1') }}</li>
            <li><span class="mdi mdi-shield-account-outline" aria-hidden="true" />{{ t('landing.hero.point2') }}</li>
            <li><span class="mdi mdi-clipboard-text-clock-outline" aria-hidden="true" />{{ t('landing.hero.point3') }}</li>
          </ul>
        </div>

        <div class="hero-visual">
          <NetworkMap />
        </div>
      </section>

      <section id="features" class="features wrap" aria-labelledby="features-title">
        <div class="section-head">
          <h2 id="features-title" class="section-title">{{ t('landing.features.title') }}</h2>
          <p class="section-lead">{{ t('landing.features.lead') }}</p>
        </div>

        <div class="bento">
          <article class="tile tile--issues">
            <div class="tile-copy">
              <h3 class="tile-title">{{ t('landing.features.issues.title') }}</h3>
              <p class="tile-text">{{ t('landing.features.issues.text') }}</p>
            </div>
            <div class="issues-visual">
              <IssuesMiniMap />
              <ol class="timeline">
                <li class="timeline-item timeline-item--open">{{ t('landing.features.issues.timeline1') }}</li>
                <li class="timeline-item">{{ t('landing.features.issues.timeline2') }}</li>
                <li class="timeline-item timeline-item--done">{{ t('landing.features.issues.timeline3') }}</li>
              </ol>
              <div class="legend">
                <span class="legend-item"><i class="dot dot--open" />{{ t('landing.features.issues.open') }}</span>
                <span class="legend-item"><i class="dot dot--done" />{{ t('landing.features.issues.resolved') }}</span>
              </div>
            </div>
          </article>

          <article class="tile tile--attendance">
            <h3 class="tile-title">{{ t('landing.features.attendance.title') }}</h3>
            <p class="tile-text">{{ t('landing.features.attendance.text') }}</p>
            <div class="cal" aria-hidden="true">
              <i v-for="(cell, index) in attendanceCells" :key="index" class="cal-cell" :class="`cal-cell--${cell}`" />
            </div>
          </article>

          <article class="tile tile--tasks">
            <h3 class="tile-title">{{ t('landing.features.tasks.title') }}</h3>
            <p class="tile-text">{{ t('landing.features.tasks.text') }}</p>
            <div class="bars" aria-hidden="true">
              <div class="bar-row"><span class="bar-label bar-label--long" /><span class="track"><i class="fill fill--done" style="width: 100%" /></span></div>
              <div class="bar-row"><span class="bar-label" /><span class="track"><i class="fill" style="width: 64%" /></span></div>
              <div class="bar-row"><span class="bar-label bar-label--short" /><span class="track"><i class="fill" style="width: 28%" /></span></div>
            </div>
          </article>

          <article class="tile tile--small">
            <span class="mdi mdi-file-document-outline tile-icon" aria-hidden="true" />
            <h3 class="tile-title tile-title--small">{{ t('landing.features.documents.title') }}</h3>
            <p class="tile-text">{{ t('landing.features.documents.text') }}</p>
          </article>
          <article class="tile tile--small">
            <span class="mdi mdi-school-outline tile-icon" aria-hidden="true" />
            <h3 class="tile-title tile-title--small">{{ t('landing.features.exams.title') }}</h3>
            <p class="tile-text">{{ t('landing.features.exams.text') }}</p>
          </article>
          <article class="tile tile--small">
            <span class="mdi mdi-chart-line tile-icon" aria-hidden="true" />
            <h3 class="tile-title tile-title--small">{{ t('landing.features.kpi.title') }}</h3>
            <p class="tile-text">{{ t('landing.features.kpi.text') }}</p>
          </article>
          <article class="tile tile--small">
            <span class="mdi mdi-bullhorn-outline tile-icon" aria-hidden="true" />
            <h3 class="tile-title tile-title--small">{{ t('landing.features.announcements.title') }}</h3>
            <p class="tile-text">{{ t('landing.features.announcements.text') }}</p>
          </article>
        </div>
      </section>

      <section id="mobile" class="mobile wrap" aria-labelledby="mobile-title">
        <div class="phone" aria-hidden="true">
          <div class="phone-screen">
            <div class="phone-banner">
              <span class="phone-banner-label">{{ t('landing.mobile.welcome') }}</span>
              <span class="phone-skeleton phone-skeleton--name" />
            </div>
            <div class="phone-card">
              <span class="phone-card-title">{{ t('landing.mobile.today') }}</span>
              <div class="phone-buttons">
                <span class="phone-btn phone-btn--on">{{ t('landing.mobile.checkin') }}</span>
                <span class="phone-btn">{{ t('landing.mobile.checkout') }}</span>
              </div>
            </div>
            <div class="phone-card">
              <span class="phone-skeleton phone-skeleton--wide" />
              <span class="phone-skeleton" />
            </div>
            <div class="phone-card">
              <span class="phone-skeleton phone-skeleton--wide" />
              <span class="phone-skeleton phone-skeleton--short" />
            </div>
            <div class="phone-nav">
              <span class="mdi mdi-home phone-nav-on" />
              <span class="mdi mdi-fingerprint" />
              <span class="mdi mdi-checkbox-marked-circle-plus-outline" />
              <span class="mdi mdi-dots-horizontal" />
            </div>
          </div>
        </div>

        <div class="mobile-copy">
          <h2 id="mobile-title" class="section-title">{{ t('landing.mobile.title') }}</h2>
          <p class="section-lead">{{ t('landing.mobile.text') }}</p>
        </div>
      </section>

      <section class="trust wrap" aria-labelledby="trust-title">
        <span class="mdi mdi-lock-outline trust-icon" aria-hidden="true" />
        <div>
          <h2 id="trust-title" class="trust-title">{{ t('landing.trust.title') }}</h2>
          <p class="section-lead">{{ t('landing.trust.text') }}</p>
        </div>
      </section>

      <section class="closing wrap" aria-labelledby="cta-title">
        <div class="closing-panel">
          <h2 id="cta-title" class="closing-title">{{ t('landing.cta.title') }}</h2>
          <p class="closing-text">{{ t('landing.cta.text') }}</p>
          <router-link :to="entryRoute" class="btn btn--light">{{ heroCta }}</router-link>
        </div>
      </section>
    </main>

    <footer class="foot wrap">
      <img :src="logoUrl" alt="" class="foot-logo" />
      <span class="foot-text">© {{ new Date().getFullYear() }} {{ t('landing.footer.rights') }}</span>
      <router-link :to="{ name: 'privacy-policy' }" class="foot-link">{{ t('privacyPolicy.title') }}</router-link>
    </footer>
  </div>
</template>

<style scoped>
.landing {
  --ink: #0f2440;
  --ink-soft: #4a5f7a;
  --mist: #f3f7fb;
  --paper: #ffffff;
  --night: #0a1a31;
  --line: #dbe5ef;
  --steel: #3e60a9;
  --brand: #1e3a5f;
  --blue: #489bcd;
  --green: #9acc48;
  --display: 'Unbounded', 'Onest Variable', system-ui, sans-serif;

  min-height: 100vh;
  background: var(--mist);
  color: var(--ink);
  font-family: 'Onest Variable', system-ui, -apple-system, 'Segoe UI', sans-serif;
  font-size: 1rem;
  line-height: 1.55;
  color-scheme: light;
}

.landing :where(h1, h2, h3, p, ul, ol) {
  margin: 0;
  padding: 0;
}

.landing :where(ul, ol) {
  list-style: none;
}

.landing a:not(.btn) {
  color: inherit;
}

.landing a {
  text-decoration: none;
}

.wrap {
  width: min(1200px, 100% - 48px);
  margin-inline: auto;
}

:focus-visible {
  outline: 2px solid var(--steel);
  outline-offset: 3px;
  border-radius: 6px;
}

/* ---------- header ---------- */
.bar {
  position: sticky;
  top: 0;
  z-index: 10;
  transition: background-color 0.2s ease, box-shadow 0.2s ease;
}

.bar--scrolled {
  background: rgba(243, 247, 251, 0.86);
  backdrop-filter: blur(14px);
  box-shadow: 0 1px 0 var(--line);
}

.bar-inner {
  display: flex;
  align-items: center;
  gap: 32px;
  height: 72px;
}

.brand-logo {
  display: block;
  height: 46px;
  width: auto;
}

.bar-nav {
  display: flex;
  gap: 28px;
  margin-right: auto;
  font-weight: 500;
  color: var(--ink-soft);
}

.bar-nav a:hover {
  color: var(--ink);
}

.bar-actions {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-left: auto;
}

.bar-nav + .bar-actions {
  margin-left: 0;
}

.lang {
  display: flex;
  gap: 2px;
  padding: 3px;
  border-radius: 999px;
  background: rgba(15, 36, 64, 0.06);
}

.lang-btn {
  border: 0;
  background: transparent;
  color: var(--ink-soft);
  font: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  padding: 4px 11px;
  border-radius: 999px;
  cursor: pointer;
}

.lang-btn[aria-pressed='true'] {
  background: var(--paper);
  color: var(--ink);
  box-shadow: 0 1px 3px rgba(15, 36, 64, 0.16);
}

/* ---------- buttons ---------- */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font: inherit;
  font-weight: 600;
  padding: 14px 26px;
  border-radius: 14px;
  border: 1.5px solid transparent;
  cursor: pointer;
  transition: transform 0.15s ease, background-color 0.15s ease, border-color 0.15s ease;
}

.btn--sm {
  padding: 8px 18px;
  border-radius: 12px;
  font-size: 0.9375rem;
}

.btn--primary {
  background: var(--brand);
  color: #fff;
}

.btn--primary:hover {
  background: #16304f;
  transform: translateY(-1px);
}

.btn--outline {
  border-color: var(--brand);
  color: var(--brand);
}

.btn--outline:hover {
  background: var(--brand);
  color: #fff;
}

.btn--ghost {
  color: var(--ink);
  padding-inline: 8px;
}

.btn--ghost:hover .mdi {
  transform: translateY(2px);
}

.btn--ghost .mdi {
  transition: transform 0.15s ease;
}

.btn--light {
  background: #fff;
  color: var(--night);
}

.btn--light:hover {
  transform: translateY(-1px);
  background: #eef5fb;
}

/* ---------- hero ---------- */
.hero {
  display: grid;
  grid-template-columns: minmax(0, 5fr) minmax(0, 6fr);
  align-items: center;
  gap: 56px;
  padding-block: 40px 96px;
}

.hero-title {
  font-family: var(--display);
  font-weight: 600;
  font-size: clamp(1.7rem, 2.9vw, 2.65rem);
  line-height: 1.16;
  letter-spacing: -0.01em;
  text-wrap: balance;
}

.hero-lead {
  margin-top: 24px;
  max-width: 34rem;
  font-size: 1.125rem;
  color: var(--ink-soft);
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin-top: 36px;
}

.hero-points {
  display: grid;
  gap: 10px;
  margin-top: 40px;
  font-size: 0.9375rem;
  color: var(--ink-soft);
}

.hero-points li {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.hero-points .mdi {
  color: var(--steel);
  font-size: 1.25rem;
}

.hero-visual {
  border-radius: 28px;
  background: var(--night);
  padding: 20px;
  box-shadow: 0 44px 80px -36px rgba(15, 36, 64, 0.55);
}

/* ---------- features ---------- */
.features {
  padding-block: 40px 104px;
  scroll-margin-top: 72px;
}

.section-head {
  display: grid;
  grid-template-columns: minmax(0, 6fr) minmax(0, 5fr);
  align-items: end;
  gap: 40px;
  margin-bottom: 48px;
}

.section-title {
  font-family: var(--display);
  font-weight: 600;
  font-size: clamp(1.5rem, 2.5vw, 2.2rem);
  line-height: 1.2;
  letter-spacing: -0.01em;
  text-wrap: balance;
}

.section-lead {
  color: var(--ink-soft);
  font-size: 1.0625rem;
  max-width: 36rem;
}

.bento {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 16px;
}

.tile {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 28px;
  border-radius: 22px;
  background: var(--paper);
  border: 1px solid var(--line);
  transition: border-color 0.2s ease;
}

.tile:hover {
  border-color: #b7cbe0;
}

.tile-title {
  font-size: 1.3125rem;
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: -0.01em;
}

.tile-title--small {
  font-size: 1.0625rem;
}

.tile-text {
  color: var(--ink-soft);
  font-size: 0.9688rem;
}

.tile--issues {
  grid-column: span 7;
  grid-row: span 2;
  padding: 32px 32px 0;
  border-radius: 28px;
  background: var(--night);
  border-color: var(--night);
  color: #fff;
  overflow: hidden;
  gap: 24px;
}

.tile--issues:hover {
  border-color: var(--night);
}

.tile--issues .tile-title {
  font-size: 1.75rem;
}

.tile--issues .tile-text {
  color: rgba(224, 236, 250, 0.78);
  max-width: 34rem;
}

.issues-visual {
  position: relative;
  flex: 1;
  min-height: 250px;
  margin: 0 -32px;
}

.timeline {
  position: absolute;
  right: 24px;
  bottom: 24px;
  width: 224px;
  padding: 14px 16px 14px 18px;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.97);
  color: var(--ink);
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: 0 18px 40px -18px rgba(0, 0, 0, 0.6);
}

.timeline-item {
  position: relative;
  padding: 5px 0 5px 20px;
}

.timeline-item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  width: 9px;
  height: 9px;
  margin-top: -4.5px;
  border-radius: 50%;
  background: var(--blue);
}

.timeline-item::after {
  content: '';
  position: absolute;
  left: 4px;
  top: calc(50% + 6px);
  width: 1px;
  height: 22px;
  background: var(--line);
}

.timeline-item:last-child::after {
  display: none;
}

.timeline-item--open::before {
  background: #f0616d;
}

.timeline-item--done::before {
  background: var(--green);
}

.legend {
  position: absolute;
  left: 24px;
  bottom: 24px;
  display: flex;
  gap: 16px;
  padding: 8px 14px;
  border-radius: 999px;
  background: rgba(10, 26, 49, 0.82);
  backdrop-filter: blur(6px);
  font-size: 0.8125rem;
  color: rgba(224, 236, 250, 0.9);
}

.legend-item {
  display: inline-flex;
  align-items: center;
  gap: 7px;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.dot--open {
  background: #f0616d;
}

.dot--done {
  background: var(--green);
}

.tile--attendance,
.tile--tasks {
  grid-column: span 5;
  padding: 26px 28px;
  border-radius: 24px;
}

.cal {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
  margin-top: auto;
  padding-top: 14px;
}

.cal-cell {
  aspect-ratio: 1.6;
  border-radius: 6px;
  background: var(--line);
}

.cal-cell--p {
  background: var(--green);
}

.cal-cell--l {
  background: #f2b441;
}

.cal-cell--a {
  background: #f0616d;
}

.bars {
  display: grid;
  gap: 10px;
  margin-top: auto;
  padding-top: 14px;
}

.bar-row {
  display: grid;
  grid-template-columns: 96px 1fr;
  align-items: center;
  gap: 14px;
}

.bar-label {
  height: 8px;
  width: 78%;
  border-radius: 4px;
  background: #cfdbe8;
}

.bar-label--long {
  width: 100%;
}

.bar-label--short {
  width: 52%;
}

.track {
  display: block;
  height: 8px;
  border-radius: 4px;
  background: #e6eef6;
  overflow: hidden;
}

.fill {
  display: block;
  height: 100%;
  border-radius: 4px;
  background: var(--steel);
}

.fill--done {
  background: var(--green);
}

.tile--small {
  grid-column: span 3;
  padding: 22px 24px 26px;
  border-radius: 18px;
}

.tile-icon {
  display: inline-grid;
  place-items: center;
  width: 42px;
  height: 42px;
  margin-bottom: 8px;
  border-radius: 12px;
  background: #e8f1f9;
  color: var(--steel);
  font-size: 1.5rem;
}

/* ---------- mobile ---------- */
.mobile {
  display: grid;
  grid-template-columns: minmax(0, 5fr) minmax(0, 6fr);
  align-items: center;
  gap: 64px;
  padding-block: 24px 104px;
  scroll-margin-top: 72px;
}

.mobile-copy .section-lead {
  margin-top: 20px;
}

.phone {
  justify-self: center;
  width: 272px;
  padding: 9px;
  border-radius: 42px;
  background: var(--ink);
  box-shadow: 0 44px 70px -34px rgba(15, 36, 64, 0.6);
}

.phone-screen {
  display: flex;
  flex-direction: column;
  gap: 10px;
  height: 460px;
  padding: 14px 12px 0;
  border-radius: 34px;
  background: #faf9ff;
  overflow: hidden;
}

.phone-banner {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 18px 16px;
  margin-top: 8px;
  border-radius: 18px;
  background: linear-gradient(135deg, var(--brand), #3b6ea5);
  color: rgba(255, 255, 255, 0.82);
  font-size: 0.8125rem;
}

.phone-skeleton {
  display: block;
  height: 9px;
  width: 58%;
  border-radius: 5px;
  background: #d8e2ee;
}

.phone-skeleton--name {
  width: 44%;
  height: 13px;
  background: rgba(255, 255, 255, 0.9);
}

.phone-skeleton--wide {
  width: 82%;
}

.phone-skeleton--short {
  width: 36%;
}

.phone-card {
  display: flex;
  flex-direction: column;
  gap: 9px;
  padding: 14px;
  border-radius: 16px;
  background: #f0f3f8;
}

.phone-card-title {
  font-size: 0.8125rem;
  font-weight: 700;
}

.phone-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.phone-btn {
  padding: 7px 0;
  border-radius: 10px;
  border: 1px solid #d3dce8;
  color: #9aa9bb;
  font-size: 0.75rem;
  font-weight: 600;
  text-align: center;
}

.phone-btn--on {
  border-color: var(--brand);
  background: var(--brand);
  color: #fff;
}

.phone-nav {
  display: flex;
  justify-content: space-around;
  margin: auto -12px 0;
  padding: 14px 0 16px;
  background: #fff;
  color: #6b7c92;
  font-size: 1.35rem;
}

.phone-nav-on {
  color: var(--brand);
}

/* ---------- trust ---------- */
.trust {
  display: flex;
  align-items: flex-start;
  gap: 24px;
  padding: 36px 40px;
  margin-bottom: 96px;
  border-radius: 24px;
  background: #e6eff8;
}

.trust-icon {
  display: inline-grid;
  place-items: center;
  flex: none;
  width: 52px;
  height: 52px;
  border-radius: 16px;
  background: var(--paper);
  color: var(--steel);
  font-size: 1.6rem;
}

.trust-title {
  font-size: 1.375rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  margin-bottom: 6px;
}

/* ---------- closing ---------- */
.closing {
  padding-bottom: 72px;
}

.closing-panel {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 14px;
  padding: 64px 56px;
  border-radius: 32px;
  background: var(--night);
  color: #fff;
  box-shadow: 0 44px 80px -40px rgba(15, 36, 64, 0.6);
}

.closing-title {
  font-family: var(--display);
  font-weight: 600;
  font-size: clamp(1.5rem, 2.6vw, 2.25rem);
  line-height: 1.2;
  max-width: 40rem;
  text-wrap: balance;
}

.closing-text {
  color: rgba(224, 236, 250, 0.78);
  font-size: 1.0625rem;
  margin-bottom: 14px;
}

/* ---------- footer ---------- */
.foot {
  display: flex;
  align-items: center;
  gap: 20px;
  padding-block: 28px 44px;
  border-top: 1px solid var(--line);
  color: var(--ink-soft);
  font-size: 0.875rem;
}

.foot-logo {
  height: 34px;
  width: auto;
}

.foot-text {
  margin-right: auto;
}

.foot-link:hover {
  color: var(--ink);
}

/* ---------- responsive ---------- */
@media (max-width: 1023px) {
  .hero {
    grid-template-columns: 1fr;
    gap: 40px;
    padding-bottom: 72px;
  }

  .section-head {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  .bento {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .tile--issues {
    grid-column: span 2;
    grid-row: auto;
  }

  .tile--attendance,
  .tile--tasks,
  .tile--small {
    grid-column: span 1;
  }

  .mobile {
    grid-template-columns: 1fr;
    gap: 40px;
  }

  .mobile-copy {
    order: -1;
  }
}

@media (max-width: 719px) {
  .bar-nav {
    display: none;
  }

  .bar-nav + .bar-actions {
    margin-left: auto;
  }

  .bar-inner {
    gap: 12px;
  }

  .brand-logo {
    height: 40px;
  }

  .bento {
    grid-template-columns: 1fr;
  }

  .tile--issues,
  .tile--attendance,
  .tile--tasks,
  .tile--small {
    grid-column: span 1;
  }

  .tile--issues {
    padding: 24px 22px 0;
  }

  .issues-visual {
    margin: 0 -22px;
    min-height: 300px;
  }

  .timeline {
    right: 12px;
    bottom: 12px;
    width: 190px;
  }

  .legend {
    left: 12px;
    bottom: auto;
    top: 12px;
  }

  .trust {
    flex-direction: column;
    padding: 28px 24px;
  }

  .closing-panel {
    padding: 44px 28px;
  }

  .foot {
    flex-wrap: wrap;
  }

  .foot-text {
    order: 3;
    flex-basis: 100%;
  }
}

@media (max-width: 479px) {
  .lang-btn {
    padding: 4px 8px;
  }

  .btn--sm {
    padding: 7px 14px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .btn,
  .btn--ghost .mdi,
  .bar {
    transition: none;
  }
}
</style>

<template>
  <div>
    <AppHeader />
    <main>
      <HeroSection />
      <PlansSection />
      <SolutionsSection />
      <MapSection />
    </main>
    <AppFooter />
  </div>
</template>

<script setup>
import AppHeader from '../components/AppHeader.vue'
import HeroSection from '../components/HeroSection.vue'
import PlansSection from '../components/PlansSection.vue'
import SolutionsSection from '../components/SolutionsSection.vue'
import MapSection from '../components/MapSection.vue'
import AppFooter from '../components/AppFooter.vue'
</script>
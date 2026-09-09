<template>
  <div>
    <AppHeader />
    <main>
      <!--
        Section order follows the reference site's argument, which the client
        praised: state the problem, answer it, show the price, then prove the
        difference. What changed is the execution — a dark, hairline-driven
        layout instead of pastel shadowed cards, and no claim we cannot back.
      -->
      <HeroSection />
      <PainPointsSection />
      <AdvantagesSection />
      <PlansSection />
      <ComparisonSection />
      <DefenseSection />
      <SolutionsSection />
      <MapSection />
      <FaqSection />
      <ContactCta />
    </main>
    <AppFooter />
  </div>
</template>

<script setup>
import AppHeader from '../components/AppHeader.vue'
import HeroSection from '../components/HeroSection.vue'
import PainPointsSection from '../components/PainPointsSection.vue'
import AdvantagesSection from '../components/AdvantagesSection.vue'
import PlansSection from '../components/PlansSection.vue'
import ComparisonSection from '../components/ComparisonSection.vue'
import DefenseSection from '../components/DefenseSection.vue'
import SolutionsSection from '../components/SolutionsSection.vue'
import MapSection from '../components/MapSection.vue'
import FaqSection from '../components/FaqSection.vue'
import ContactCta from '../components/ContactCta.vue'
import AppFooter from '../components/AppFooter.vue'
</script>

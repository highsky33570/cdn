import { onMounted, onUnmounted, ref } from 'vue'

/**
 * Reveal-on-scroll, shared by the landing sections.
 *
 * Each section used to carry its own copy of this IntersectionObserver block,
 * which meant the threshold and the unobserve-after-first-hit behaviour drifted
 * between them. One implementation, one behaviour.
 *
 * Honours prefers-reduced-motion by revealing immediately: the animation is
 * decoration, the content is not.
 */
export function useReveal(threshold = 0.15) {
  const sectionRef = ref(null)
  const isVisible = ref(false)
  let observer = null

  onMounted(() => {
    const reduced =
      typeof window !== 'undefined' &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches

    if (reduced || typeof IntersectionObserver === 'undefined') {
      isVisible.value = true

      return
    }

    observer = new IntersectionObserver(
      ([entry]) => {
        if (!entry.isIntersecting) return

        isVisible.value = true

        if (sectionRef.value) observer.unobserve(sectionRef.value)
      },
      { threshold },
    )

    if (sectionRef.value) observer.observe(sectionRef.value)
  })

  onUnmounted(() => {
    if (observer) observer.disconnect()
  })

  return { sectionRef, isVisible }
}

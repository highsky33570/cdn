import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import ForgotPasswordView from '../views/ForgotPasswordView.vue'
import LoginView from '../views/LoginView.vue'
import PlanDetailView from '../views/PlanDetailView.vue'
import PlansView from '../views/PlansView.vue'
import RegisterView from '../views/RegisterView.vue'
import ResetPasswordView from '../views/ResetPasswordView.vue'
import TwoFactorChallengeView from '../views/TwoFactorChallengeView.vue'
import VerifyEmailView from '../views/VerifyEmailView.vue'

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior(to) {
    if (to.hash && !to.hash.includes('=')) {
      return {
        el: to.hash,
        behavior: 'smooth',
        top: 84,
      }
    }

    return { top: 0 }
  },
  routes: [
    {
      path: '/',
      component: HomeView,
    },
    {
      path: '/plans',
      component: PlansView,
    },
    {
      path: '/plans/:slug',
      component: PlanDetailView,
    },
    {
      path: '/plans/japan-cdn',
      redirect: '/plans/jpn-pro',
    },
    {
      path: '/login',
      component: LoginView,
    },
    {
      path: '/forgot-password',
      component: ForgotPasswordView,
    },
    {
      path: '/register',
      component: RegisterView,
    },
    {
      path: '/reset-password/:token',
      component: ResetPasswordView,
    },
    {
      path: '/verify-email',
      component: VerifyEmailView,
    },
    {
      path: '/two-factor-challenge',
      component: TwoFactorChallengeView,
    },
  ],
})

export default router

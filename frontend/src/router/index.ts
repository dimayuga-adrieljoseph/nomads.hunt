import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    // ── Public ──────────────────────────────────────────────────────────────
    {
      path: '/',
      name: 'home',
      component: () => import('@/views/HomeView.vue'),
    },
    {
      path: '/catalog',
      name: 'catalog',
      component: () => import('@/views/CatalogView.vue'),
    },
    {
      path: '/products/:id',
      name: 'product-detail',
      component: () => import('@/views/ProductDetailView.vue'),
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/RegisterView.vue'),
      meta: { guestOnly: true },
    },

    // ── Customer (auth required) ─────────────────────────────────────────────
    {
      path: '/liked-products',
      name: 'liked-products',
      component: () => import('@/views/LikedProductsView.vue'),
      meta: { requiresAuth: true, requiresCustomer: true },
    },
    {
      path: '/my-claims',
      redirect: { name: 'liked-products' },
    },
    {
      path: '/my-orders',
      name: 'my-orders',
      component: () => import('@/views/MyOrdersView.vue'),
      meta: { requiresAuth: true, requiresCustomer: true },
    },
    {
      path: '/orders/:id',
      name: 'order-detail',
      component: () => import('@/views/OrderDetailView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/orders/:id/pay',
      name: 'payment',
      component: () => import('@/views/PaymentView.vue'),
      meta: { requiresAuth: true, requiresCustomer: true },
    },
    {
      path: '/receipt/:id',
      name: 'receipt',
      component: () => import('@/views/ReceiptView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/profile',
      name: 'profile',
      component: () => import('@/views/ProfileView.vue'),
      meta: { requiresAuth: true, requiresCustomer: true },
    },

    // ── Admin ────────────────────────────────────────────────────────────────
    {
      path: '/admin',
      component: () => import('@/views/admin/AdminLayout.vue'),
      meta: { requiresAuth: true, requiresAdmin: true },
      children: [
        {
          path: '',
          name: 'admin-dashboard',
          component: () => import('@/views/admin/DashboardView.vue'),
        },
        {
          path: 'products',
          name: 'admin-products',
          component: () => import('@/views/admin/ProductsView.vue'),
        },
        {
          path: 'products/new',
          name: 'admin-product-new',
          component: () => import('@/views/admin/ProductFormView.vue'),
        },
        {
          path: 'products/:id/edit',
          name: 'admin-product-edit',
          component: () => import('@/views/admin/ProductFormView.vue'),
        },
        {
          path: 'announcements',
          name: 'admin-announcements',
          component: () => import('@/views/admin/AnnouncementsView.vue'),
        },
        {
          path: 'announcements/new',
          name: 'admin-announcement-new',
          component: () => import('@/views/admin/AnnouncementFormView.vue'),
        },
        {
          path: 'announcements/:id/edit',
          name: 'admin-announcement-edit',
          component: () => import('@/views/admin/AnnouncementFormView.vue'),
        },
        {
          path: 'claims',
          name: 'admin-claims',
          component: () => import('@/views/admin/ClaimsView.vue'),
        },
        {
          path: 'products/:id/claims',
          name: 'admin-claim-detail',
          component: () => import('@/views/admin/ClaimDetailView.vue'),
        },
        {
          path: 'orders',
          name: 'admin-orders',
          component: () => import('@/views/admin/OrdersView.vue'),
        },
        {
          path: 'customers',
          name: 'admin-customers',
          component: () => import('@/views/admin/CustomersView.vue'),
        },
        {
          path: 'customers/:id',
          name: 'admin-customer-detail',
          component: () => import('@/views/admin/CustomerDetailView.vue'),
        },
        {
          path: 'activity',
          name: 'admin-activity',
          component: () => import('@/views/admin/ActivityView.vue'),
        },
      ],
    },

    // ── Catch-all ────────────────────────────────────────────────────────────
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
})

// ── Navigation Guards ─────────────────────────────────────────────────────────

router.beforeEach((to, _from, next) => {
  const auth = useAuthStore()

  if (to.meta.guestOnly && auth.isLoggedIn) {
    return next(auth.isAdmin ? '/admin' : '/catalog')
  }

  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return next('/')
  }

  if (to.meta.requiresCustomer && !auth.isCustomer) {
    return next('/')
  }

  next()
})

export default router

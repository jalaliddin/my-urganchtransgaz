import { createRouter, createWebHistory } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      component: () => import('@/layouts/AuthLayout.vue'),
      meta: { guestOnly: true },
      children: [
        {
          path: 'login',
          name: 'login',
          component: () => import('@/views/auth/LoginView.vue'),
        },
        {
          path: 'reset-password',
          name: 'reset-password',
          component: () => import('@/views/auth/ResetPasswordView.vue'),
        },
      ],
    },
    {
      path: '/',
      component: () => import('@/layouts/DefaultLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/dashboard/DashboardView.vue'),
        },
        {
          path: 'organizations',
          name: 'organizations',
          component: () => import('@/views/organizations/OrganizationsView.vue'),
        },
        {
          path: 'departments',
          name: 'departments',
          component: () => import('@/views/departments/DepartmentsView.vue'),
        },
        {
          path: 'employees',
          name: 'employees',
          component: () => import('@/views/employees/EmployeesView.vue'),
        },
        {
          path: 'profile',
          name: 'profile',
          component: () => import('@/views/profile/ProfileView.vue'),
        },
        {
          path: 'attendance',
          name: 'attendance',
          component: () => import('@/views/attendance/AttendanceView.vue'),
        },
        {
          path: 'tasks',
          name: 'tasks',
          component: () => import('@/views/tasks/TasksView.vue'),
        },
        {
          path: 'tasks/:id',
          name: 'task-detail',
          component: () => import('@/views/tasks/TaskDetailView.vue'),
        },
        {
          path: 'exams',
          name: 'exams',
          component: () => import('@/views/exams/ExamsView.vue'),
        },
        {
          path: 'exams/:id/attempt',
          name: 'exam-attempt',
          component: () => import('@/views/exams/ExamAttemptView.vue'),
        },
        {
          path: 'exams/:id/results',
          name: 'exam-results',
          component: () => import('@/views/exams/ExamResultsView.vue'),
        },
        {
          path: 'kpi',
          name: 'kpi',
          component: () => import('@/views/kpi/KpiView.vue'),
        },
        {
          path: 'kpi/report',
          name: 'kpi-report',
          component: () => import('@/views/kpi/KpiReportView.vue'),
        },
        {
          path: 'announcements',
          name: 'announcements',
          component: () => import('@/views/announcements/AnnouncementsView.vue'),
        },
        {
          path: 'announcements/:id',
          name: 'announcement-detail',
          component: () => import('@/views/announcements/AnnouncementDetailView.vue'),
        },
        {
          path: 'leave-requests',
          name: 'leave-requests',
          component: () => import('@/views/leave-requests/LeaveRequestsView.vue'),
        },
        {
          path: 'business-trips',
          name: 'business-trips',
          component: () => import('@/views/business-trips/BusinessTripsView.vue'),
        },
        {
          path: 'documents',
          name: 'documents',
          component: () => import('@/views/documents/DocumentsView.vue'),
        },
        {
          path: 'change-requests',
          name: 'change-requests',
          component: () => import('@/views/change-requests/ChangeRequestsView.vue'),
        },
        {
          path: 'notifications',
          name: 'notifications',
          component: () => import('@/views/notifications/NotificationsView.vue'),
        },
      ],
    },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  return true
})

export default router

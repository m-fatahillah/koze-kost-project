<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useDark, useToggle } from '@vueuse/core'

const page = usePage()

const isDark = useDark()
const toggleDark = useToggle(isDark)

const userAvatarSrc = computed(() => {
  const avatar = page.props.auth?.user?.avatar

  if (!avatar) return undefined
  if (avatar.startsWith('http://') || avatar.startsWith('https://') || avatar.startsWith('/storage/')) return avatar

  return `/storage/${avatar}`
})

const handleNavigate = (path) => {
  router.get(path)
}

const hasRole = (roles) => {
  const userRoles = page.props.auth?.user?.roles || []
  if (!roles) return true // no role restriction
  if (Array.isArray(roles)) {
    return roles.some(role => userRoles.includes(role))
  }
  return userRoles.includes(roles)
}

const navigationItems = computed(() => {
  const items = [
    [
      {
        label: 'Menu Utama',
        type: 'label',
      },
      {
        label: 'Dasbor',
        icon: 'i-lucide-layout-dashboard',
        onSelect: () => handleNavigate('/dashboard'),
        active: page.url === '/dashboard',
        show: true,
      },
      {
        label: 'Fasilitas',
        icon: 'i-lucide-sparkles',
        onSelect: () => handleNavigate('/facilities'),
        active: page.url?.startsWith('/facilities'),
        show: hasRole('Admin'),
      },
      {
        label: 'Tipe Properti',
        icon: 'i-lucide-building',
        onSelect: () => handleNavigate('/property-types'),
        active: page.url?.startsWith('/property-types'),
        show: hasRole('Admin'),
      },
      {
        label: 'Properti',
        icon: 'i-lucide-home',
        onSelect: () => handleNavigate('/properties'),
        active: page.url?.startsWith('/properties'),
        show: hasRole(['Admin', 'Owner']),
      },
      {
        label: 'Iklan Eksklusif',
        icon: 'i-lucide-star',
        onSelect: () => handleNavigate('/featured-listing'),
        active: page.url === '/featured-listing' || page.url?.startsWith('/featured-listing?'),
        show: hasRole(['Admin', 'Owner']),
      },
      {
        label: 'Pesan',
        icon: 'i-lucide-message-square',
        onSelect: () => handleNavigate('/messages'),
        active: page.url?.startsWith('/messages'),
        show: hasRole('Owner'),
      },
      {
        label: 'Pengaturan Iklan',
        icon: 'i-lucide-settings',
        onSelect: () => handleNavigate('/featured-listing/settings'),
        active: page.url?.startsWith('/featured-listing/settings'),
        show: hasRole('Admin'),
      },
      {
        label: 'Iklan',
        icon: 'i-lucide-megaphone',
        onSelect: () => handleNavigate('/advertisements'),
        active: page.url?.startsWith('/advertisements'),
        show: hasRole('Admin'),
      },
      {
        label: 'Pengguna',
        icon: 'i-lucide-user-cog',
        onSelect: () => handleNavigate('/users'),
        active: page.url?.startsWith('/users'),
        show: hasRole('Admin'),
      },
    ],
    [
      {
        label: 'Lainnya',
        type: 'label',
        show: hasRole('Admin')
      },
      {
        label: 'FAQ',
        icon: 'i-lucide-help-circle',
        onSelect: () => handleNavigate('/faqs'),
        active: page.url?.startsWith('/faqs'),
        show: hasRole('Admin'),
      },
    ],
  ]

  // Filter out items that are hidden by role, and remove empty groups
  return items
    .map(group => group.filter(item => item.show !== false))
    .filter(group => group.length > 0 && group.some(item => item.type !== 'label'))
})

const handleLogout = () => {
  router.post('/logout')
}

const handleOpenProfile = () => {
  router.get('/profile')
}

const userMenuItems = [
  [
    {
      label: 'Profil',
      icon: 'i-lucide-user',
      onSelect: handleOpenProfile,
    },
    {
      label: 'Pengaturan',
      icon: 'i-lucide-settings',
    },
  ],
  [
    {
      label: 'Keluar',
      icon: 'i-lucide-log-out',
      onSelect: handleLogout,
    },
  ],
]
</script>

<template>
  <UApp>
    <UDashboardGroup>
      <UDashboardSidebar
        id="dashboard-sidebar"
        collapsible
        resizable
        :min-size="15"
        :default-size="20"
        :max-size="25"
      >
        <template #header>
          <div class="flex items-center pl-0 pt-3 -ml-2">
            <img 
              src="/images/logo-koze-new.png" 
              alt="Logo Koze" 
              class="h-24 w-auto object-contain -my-4" />
          </div>
        </template>

        <template #default>
          <UNavigationMenu
            :items="navigationItems"
            orientation="vertical"
            class="w-full"
          />
        </template>

        <template #footer="{ collapsed }">
          <div class="flex w-full flex-col gap-1">
            <UButton
              variant="ghost"
              color="neutral"
              block
              :square="collapsed"
              class="justify-start gap-2"
              :icon="isDark ? 'i-lucide-sun' : 'i-lucide-moon'"
              @click="toggleDark()"
            >
              <span v-if="!collapsed" class="truncate text-sm">{{ isDark ? 'Mode Terang' : 'Mode Gelap' }}</span>
            </UButton>

            <UDropdownMenu :items="userMenuItems">
              <UButton variant="ghost" block :square="collapsed" class="justify-start gap-2">
                <UAvatar
                  :alt="page.props.auth?.user?.name || 'User'"
                  :src="userAvatarSrc"
                  size="2xs"
                />
                <span v-if="!collapsed" class="truncate text-sm">{{ page.props.auth?.user?.name || 'User' }}</span>
              </UButton>
            </UDropdownMenu>
          </div>
        </template>
      </UDashboardSidebar>

      <UDashboardPanel>
        <template #body>
          <div class="p-6">
            <slot />
          </div>
        </template>
      </UDashboardPanel>
    </UDashboardGroup>
  </UApp>
</template>

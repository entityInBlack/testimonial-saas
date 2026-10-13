<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="__('Profile')"
            data-testid="profile-page-header"
        />
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="p-5 sm:p-8 rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="p-5 sm:p-8 rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <div class="p-5 sm:p-8 rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

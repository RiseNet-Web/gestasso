<script lang="ts">
	import { auth } from '$lib/stores/auth.svelte';
	import { goto } from '$app/navigation';
	import { Menu, X, User, LogOut } from '@lucide/svelte';
	
	let showMobileMenu = false;
	let showUserMenu = false;
	
	function toggleMobileMenu() {
		showMobileMenu = !showMobileMenu;
	}
	
	function toggleUserMenu() {
		showUserMenu = !showUserMenu;
	}
	
	function handleLogout() {
		auth.logout();
		goto('/');
		showUserMenu = false;
	}
</script>

<header class="bg-white shadow-sm border-b border-gray-200">
	<nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
		<div class="flex justify-between items-center h-16">
			<!-- Logo -->
			<div class="flex items-center">
				<a href="/" class="flex items-center space-x-2">
					<div class="w-8 h-8 bg-sport-blue-600 rounded-lg flex items-center justify-center">
						<span class="text-white font-bold text-lg">G</span>
					</div>
					<span class="text-xl font-bold text-gray-900">GestAsso</span>
				</a>
			</div>

			<!-- Navigation Desktop -->
			<div class="hidden md:flex items-center space-x-8">
				{#if auth.user}
					<a href="/" class="text-gray-700 hover:text-sport-blue-600 transition-colors">Accueil</a>
					<a href="/join-club" class="text-gray-700 hover:text-sport-blue-600 transition-colors">Rejoindre un club</a>
					<a href="/create-club" class="text-gray-700 hover:text-sport-blue-600 transition-colors">Créer un club</a>
					
					<!-- Menu utilisateur -->
					<div class="relative">
						<button
							onclick={toggleUserMenu}
							class="flex items-center space-x-1 text-gray-700 hover:text-sport-blue-600 transition-colors"
						>
							<User size={20} />
							<span>{auth.user.firstName}</span>
						</button>
						
						{#if showUserMenu}
							<div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
								<button
									onclick={handleLogout}
									class="flex items-center space-x-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
								>
									<LogOut size={16} />
									<span>Se déconnecter</span>
								</button>
							</div>
						{/if}
					</div>
				{:else}
					<a href="/login" class="text-gray-700 hover:text-sport-blue-600 transition-colors">Connexion</a>
					<a href="/register" class="btn-primary">Inscription</a>
				{/if}
			</div>

			<!-- Menu mobile button -->
			<div class="md:hidden">
				<button onclick={toggleMobileMenu} class="text-gray-700">
					{#if showMobileMenu}
						<X size={24} />
					{:else}
						<Menu size={24} />
					{/if}
				</button>
			</div>
		</div>

		<!-- Menu mobile -->
		{#if showMobileMenu}
			<div class="md:hidden py-4 border-t border-gray-200">
				{#if auth.user}
					<div class="space-y-2">
						<a href="/" class="block py-2 text-gray-700 hover:text-sport-blue-600">Accueil</a>
						<a href="/join-club" class="block py-2 text-gray-700 hover:text-sport-blue-600">Rejoindre un club</a>
						<a href="/create-club" class="block py-2 text-gray-700 hover:text-sport-blue-600">Créer un club</a>
						<div class="border-t border-gray-200 pt-2">
							<div class="flex items-center space-x-2 py-2 text-gray-700">
								<User size={20} />
								<span>{auth.user.firstName} {auth.user.lastName}</span>
							</div>
							<button
								onclick={handleLogout}
								class="flex items-center space-x-2 w-full py-2 text-gray-700 hover:text-sport-blue-600"
							>
								<LogOut size={16} />
								<span>Se déconnecter</span>
							</button>
						</div>
					</div>
				{:else}
					<div class="space-y-2">
						<a href="/login" class="block py-2 text-gray-700 hover:text-sport-blue-600">Connexion</a>
						<a href="/register" class="block py-2 text-sport-blue-600 font-medium">Inscription</a>
					</div>
				{/if}
			</div>
		{/if}
	</nav>
</header>

<!-- Overlay pour fermer le menu utilisateur -->
{#if showUserMenu}
	<div 
		class="fixed inset-0 z-40" 
		onclick={() => showUserMenu = false}
	></div>
{/if} 
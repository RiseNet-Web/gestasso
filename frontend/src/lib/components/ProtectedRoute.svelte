<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { auth } from '$lib/stores/auth.svelte';

	interface Props {
		requiredRole?: 'owner' | 'member';
		children?: any;
	}

	let { requiredRole, children }: Props = $props();

	let isLoading = true;
	let isAuthorized = false;

	onMount(async () => {
		// Initialiser l'authentification si pas déjà fait
		if (!auth.user && !auth.isLoading) {
			await auth.initialize();
		}

		// Vérifier l'authentification
		if (!auth.user) {
			// Rediriger vers la page de connexion avec redirect
			const redirectUrl = `/login?redirect=${encodeURIComponent($page.url.pathname + $page.url.search)}`;
			goto(redirectUrl);
			return;
		}

		// Vérifier le rôle si requis
		if (requiredRole) {
			const hasRequiredRole = requiredRole === 'owner' 
				? auth.isOwner() 
				: auth.isMember();

			if (!hasRequiredRole) {
				// Rediriger vers l'inscription avec le bon rôle
				const redirectUrl = `/register?role=${requiredRole}&redirect=${encodeURIComponent($page.url.pathname + $page.url.search)}`;
				goto(redirectUrl);
				return;
			}
		}

		isAuthorized = true;
		isLoading = false;
	});

	// Surveiller les changements d'authentification
	$effect(() => {
		if (!auth.isLoading && !auth.user && !isLoading) {
			const redirectUrl = `/login?redirect=${encodeURIComponent($page.url.pathname + $page.url.search)}`;
			goto(redirectUrl);
		}
	});
</script>

{#if isLoading || auth.isLoading}
	<div class="min-h-screen flex items-center justify-center">
		<div class="text-center">
			<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
			<p class="mt-2 text-gray-600">Vérification de l'authentification...</p>
		</div>
	</div>
{:else if isAuthorized}
	{@render children?.()}
{:else}
	<div class="min-h-screen flex items-center justify-center">
		<div class="text-center">
			<h1 class="text-2xl font-bold text-gray-900 mb-2">Accès non autorisé</h1>
			<p class="text-gray-600 mb-4">Vous n'avez pas les permissions nécessaires pour accéder à cette page.</p>
			<a 
				href="/" 
				class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
			>
				Retour à l'accueil
			</a>
		</div>
	</div>
{/if} 
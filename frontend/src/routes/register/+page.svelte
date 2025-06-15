<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { enhance } from '$app/forms';

	// Données du serveur
	export let data;
	export let form;

	// État du formulaire
	let formData = {
		email: form?.formData?.email || '',
		password: '',
		confirmPassword: '',
		firstName: form?.formData?.firstName || '',
		lastName: form?.formData?.lastName || '',
		onboardingType: form?.formData?.onboardingType || 'member',
		phone: form?.formData?.phone || '',
		dateOfBirth: form?.formData?.dateOfBirth || ''
	};

	let isSubmitting = false;

	// Changement de rôle
	function changeRole(newRole: 'member' | 'owner') {
		formData.onboardingType = newRole;
		
		// Mettre à jour l'URL
		const newUrl = new URL($page.url);
		newUrl.searchParams.set('role', newRole);
		goto(newUrl.toString(), { replaceState: true });
	}
</script>

<svelte:head>
	<title>Inscription - GestAsso</title>
	<meta name="description" content="Créez votre compte pour rejoindre ou créer un club sportif" />
</svelte:head>

<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
	<div class="max-w-md w-full space-y-8">
		<div class="text-center">
			<h1 class="text-3xl font-bold text-gray-900 mb-2">Créer un compte</h1>
			<p class="text-gray-600">
				{#if formData.onboardingType === 'owner'}
					Devenez propriétaire de club et simplifiez la gestion documentaire
				{:else}
					Rejoignez un club et gérez vos documents facilement
				{/if}
			</p>
		</div>

		<!-- Sélecteur de rôle -->
		<div class="bg-white rounded-lg p-6 shadow-sm border">
			<h2 class="text-lg font-semibold text-gray-900 mb-4">Je suis...</h2>
			<div class="grid grid-cols-2 gap-3">
				<button
					type="button"
					onclick={() => changeRole('member')}
					class="p-4 rounded-lg border-2 text-center transition-all {formData.onboardingType === 'member' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'}"
				>
					<div class="text-2xl mb-2">👤</div>
					<div class="font-medium">Membre</div>
					<div class="text-sm text-gray-600">Je veux rejoindre un club</div>
				</button>
				
				<button
					type="button"
					onclick={() => changeRole('owner')}
					class="p-4 rounded-lg border-2 text-center transition-all {formData.onboardingType === 'owner' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'}"
				>
					<div class="text-2xl mb-2">👑</div>
					<div class="font-medium">Dirigeant</div>
					<div class="text-sm text-gray-600">Je veux créer/gérer un club</div>
				</button>
			</div>
		</div>

		<!-- Formulaire d'inscription -->
		<form 
			method="POST" 
			use:enhance={() => {
				isSubmitting = true;
				return async ({ update }) => {
					await update();
					isSubmitting = false;
				};
			}}
			class="bg-white rounded-lg shadow-sm border p-6 space-y-6"
		>
			<!-- Messages d'erreur généraux -->
			{#if form?.errors?.general}
				<div class="bg-red-50 border border-red-200 rounded-md p-4">
					<div class="text-red-800 text-sm">{form.errors.general}</div>
				</div>
			{/if}

			<!-- Champs du formulaire -->
			<div class="grid grid-cols-2 gap-4">
				<div>
					<label for="firstName" class="block text-sm font-medium text-gray-700 mb-1">
						Prénom *
					</label>
					<input
						id="firstName"
						name="firstName"
						type="text"
						bind:value={formData.firstName}
						required
						disabled={isSubmitting}
						class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 {form?.errors?.firstName ? 'border-red-300' : ''}"
						placeholder="Votre prénom"
					/>
					{#if form?.errors?.firstName}
						<p class="mt-1 text-sm text-red-600">{form.errors.firstName}</p>
					{/if}
				</div>

				<div>
					<label for="lastName" class="block text-sm font-medium text-gray-700 mb-1">
						Nom *
					</label>
					<input
						id="lastName"
						name="lastName"
						type="text"
						bind:value={formData.lastName}
						required
						disabled={isSubmitting}
						class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 {form?.errors?.lastName ? 'border-red-300' : ''}"
						placeholder="Votre nom"
					/>
					{#if form?.errors?.lastName}
						<p class="mt-1 text-sm text-red-600">{form.errors.lastName}</p>
					{/if}
				</div>
			</div>

			<div>
				<label for="email" class="block text-sm font-medium text-gray-700 mb-1">
					Email *
				</label>
				<input
					id="email"
					name="email"
					type="email"
					bind:value={formData.email}
					required
					disabled={isSubmitting}
					class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 {form?.errors?.email ? 'border-red-300' : ''}"
					placeholder="votre@email.com"
				/>
				{#if form?.errors?.email}
					<p class="mt-1 text-sm text-red-600">{form.errors.email}</p>
				{/if}
			</div>

			<div>
				<label for="password" class="block text-sm font-medium text-gray-700 mb-1">
					Mot de passe *
				</label>
				<input
					id="password"
					name="password"
					type="password"
					bind:value={formData.password}
					required
					disabled={isSubmitting}
					class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 {form?.errors?.password ? 'border-red-300' : ''}"
					placeholder="••••••••"
				/>
				{#if form?.errors?.password}
					<p class="mt-1 text-sm text-red-600">{form.errors.password}</p>
				{/if}
			</div>

			<div>
				<label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-1">
					Confirmer le mot de passe *
				</label>
				<input
					id="confirmPassword"
					name="confirmPassword"
					type="password"
					bind:value={formData.confirmPassword}
					required
					disabled={isSubmitting}
					class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 {form?.errors?.confirmPassword ? 'border-red-300' : ''}"
					placeholder="••••••••"
				/>
				{#if form?.errors?.confirmPassword}
					<p class="mt-1 text-sm text-red-600">{form.errors.confirmPassword}</p>
				{/if}
			</div>

			<div>
				<label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
					Téléphone (optionnel)
				</label>
				<input
					id="phone"
					name="phone"
					type="tel"
					bind:value={formData.phone}
					disabled={isSubmitting}
					class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 {form?.errors?.phone ? 'border-red-300' : ''}"
					placeholder="+33 1 23 45 67 89"
				/>
				{#if form?.errors?.phone}
					<p class="mt-1 text-sm text-red-600">{form.errors.phone}</p>
				{/if}
			</div>

			<div>
				<label for="dateOfBirth" class="block text-sm font-medium text-gray-700 mb-1">
					Date de naissance (optionnel)
				</label>
				<input
					id="dateOfBirth"
					name="dateOfBirth"
					type="date"
					bind:value={formData.dateOfBirth}
					disabled={isSubmitting}
					class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 {form?.errors?.dateOfBirth ? 'border-red-300' : ''}"
				/>
				{#if form?.errors?.dateOfBirth}
					<p class="mt-1 text-sm text-red-600">{form.errors.dateOfBirth}</p>
				{/if}
			</div>

			<!-- Champ caché pour le type d'utilisateur -->
			<input type="hidden" name="onboardingType" value={formData.onboardingType} />

			<button
				type="submit"
				disabled={isSubmitting}
				class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
			>
				{#if isSubmitting}
					<div class="flex items-center justify-center">
						<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
						Création du compte...
					</div>
				{:else}
					Créer mon compte
				{/if}
			</button>

			<div class="text-center">
				<p class="text-sm text-gray-600">
					Déjà un compte ?
					<a href="/login" class="font-medium text-blue-600 hover:text-blue-500">
						Se connecter
					</a>
				</p>
			</div>
		</form>
	</div>
</div> 
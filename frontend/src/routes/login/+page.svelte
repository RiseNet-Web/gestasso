<script lang="ts">
	import { enhance } from '$app/forms';

	// Données du serveur
	export let form;

	// État du formulaire
	let formData = {
		email: form?.formData?.email || '',
		password: ''
	};

	let isSubmitting = false;
</script>

<svelte:head>
	<title>Connexion - GestAsso</title>
	<meta name="description" content="Connectez-vous à votre compte GestAsso" />
</svelte:head>

<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
	<div class="max-w-md w-full space-y-8">
		<div class="text-center">
			<h1 class="text-3xl font-bold text-gray-900 mb-2">Connexion</h1>
			<p class="text-gray-600">Accédez à votre espace GestAsso</p>
		</div>

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

			<div>
				<label for="email" class="block text-sm font-medium text-gray-700 mb-1">
					Email
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
					Mot de passe
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

			<button
				type="submit"
				disabled={isSubmitting}
				class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
			>
				{#if isSubmitting}
					<div class="flex items-center justify-center">
						<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
						Connexion...
					</div>
				{:else}
					Se connecter
				{/if}
			</button>

			<div class="text-center">
				<p class="text-sm text-gray-600">
					Pas encore de compte ?
					<a href="/register" class="font-medium text-blue-600 hover:text-blue-500">
						S'inscrire
					</a>
				</p>
			</div>
		</form>
	</div>
</div> 
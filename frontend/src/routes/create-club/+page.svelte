<script lang="ts">
	import ProtectedRoute from '$lib/components/ProtectedRoute.svelte';
	import { goto } from '$app/navigation';
	
	// État du formulaire
	let formData = {
		name: '',
		sport: '',
		description: '',
		location: '',
		isPublic: true,
		allowRegistrations: true
	};

	let isSubmitting = false;
	let error = '';
	
	// Soumission du formulaire
	async function handleSubmit(event: SubmitEvent) {
		event.preventDefault();
		
		if (!formData.name || !formData.sport || !formData.location) {
			error = 'Veuillez remplir tous les champs obligatoires';
			return;
		}

		isSubmitting = true;
		error = '';

		try {
			// TODO: Implémenter l'appel API pour créer le club
			console.log('Création du club:', formData);
			
			// Simulation d'un délai
			await new Promise(resolve => setTimeout(resolve, 1000));
			
			// Redirection vers la page du club créé
			// TODO: Utiliser l'ID du club créé
			goto('/');
			
		} catch (err) {
			error = 'Erreur lors de la création du club';
		} finally {
			isSubmitting = false;
		}
	}
</script>

<svelte:head>
	<title>Créer un club - GestAsso</title>
	<meta name="description" content="Créez votre club sportif et simplifiez la gestion documentaire" />
</svelte:head>

<ProtectedRoute requiredRole="owner">
	<div class="min-h-screen bg-gray-50 py-8">
		<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
			<div class="bg-white shadow rounded-lg">
				<div class="px-6 py-4 border-b border-gray-200">
					<h1 class="text-2xl font-bold text-gray-900">Créer un nouveau club</h1>
					<p class="mt-1 text-sm text-gray-600">
						Configurez votre club sportif et commencez à simplifier la gestion documentaire
					</p>
				</div>

				<form onsubmit={handleSubmit} class="p-6 space-y-6">
					{#if error}
						<div class="bg-red-50 border border-red-200 rounded-md p-4">
							<div class="text-red-800 text-sm">{error}</div>
						</div>
					{/if}

					<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
						<div>
							<label for="name" class="block text-sm font-medium text-gray-700 mb-1">
								Nom du club *
							</label>
							<input
								id="name"
								type="text"
								bind:value={formData.name}
								required
								class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
								placeholder="ex: AS Saint-Martin Football"
							/>
						</div>

						<div>
							<label for="sport" class="block text-sm font-medium text-gray-700 mb-1">
								Sport *
							</label>
							<input
								id="sport"
								type="text"
								bind:value={formData.sport}
								required
								class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
								placeholder="ex: Football, Basketball, Tennis..."
							/>
						</div>
					</div>

					<div>
						<label for="location" class="block text-sm font-medium text-gray-700 mb-1">
							Localisation *
						</label>
						<input
							id="location"
							type="text"
							bind:value={formData.location}
							required
							class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
							placeholder="ex: Paris, Lyon, Marseille..."
						/>
					</div>

					<div>
						<label for="description" class="block text-sm font-medium text-gray-700 mb-1">
							Description
						</label>
						<textarea
							id="description"
							bind:value={formData.description}
							rows="4"
							class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
							placeholder="Décrivez votre club, ses valeurs, ses objectifs..."
						></textarea>
					</div>

					<div class="space-y-4">
						<h3 class="text-lg font-medium text-gray-900">Paramètres de visibilité</h3>
						
						<div class="flex items-center">
							<input
								id="isPublic"
								type="checkbox"
								bind:checked={formData.isPublic}
								class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
							/>
							<label for="isPublic" class="ml-2 text-sm text-gray-700">
								Club public (visible dans les recherches)
							</label>
						</div>

						<div class="flex items-center">
							<input
								id="allowRegistrations"
								type="checkbox"
								bind:checked={formData.allowRegistrations}
								class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
							/>
							<label for="allowRegistrations" class="ml-2 text-sm text-gray-700">
								Autoriser les demandes d'adhésion
							</label>
						</div>
					</div>

					<div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
						<a
							href="/"
							class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
						>
							Annuler
						</a>
						<button
							type="submit"
							disabled={isSubmitting}
							class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
						>
							{#if isSubmitting}
								Création en cours...
							{:else}
								Créer le club
							{/if}
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</ProtectedRoute> 
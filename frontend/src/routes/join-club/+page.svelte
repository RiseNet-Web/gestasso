<script lang="ts">
	import ProtectedRoute from '$lib/components/ProtectedRoute.svelte';
	import { goto } from '$app/navigation';

	// État de la recherche
	let searchQuery = '';
	let searchResults: any[] = [];
	let isSearching = false;
	let hasSearched = false;

	// Fonction de recherche (simulée pour l'instant)
	async function handleSearch(event: SubmitEvent) {
		event.preventDefault();
		
		if (!searchQuery.trim()) {
			return;
		}

		isSearching = true;
		hasSearched = false;

		try {
			// TODO: Implémenter l'appel API pour rechercher les clubs
			console.log('Recherche de clubs:', searchQuery);
			
			// Simulation d'un délai
			await new Promise(resolve => setTimeout(resolve, 1000));
			
			// Simulation de résultats
			searchResults = [
				{
					id: '1',
					name: 'FC Exemple',
					sport: 'Football',
					location: 'Paris',
					membersCount: 45,
					description: 'Club de football amateur'
				},
				{
					id: '2',
					name: 'Basket Club Local',
					sport: 'Basketball',
					location: 'Lyon',
					membersCount: 32,
					description: 'Club de basketball pour tous niveaux'
				}
			];
			
			hasSearched = true;
			
		} catch (err) {
			console.error('Erreur lors de la recherche:', err);
		} finally {
			isSearching = false;
		}
	}

	// Fonction pour rejoindre un club
	function joinClub(clubId: string) {
		// TODO: Implémenter la logique de demande d'adhésion
		console.log('Demande d\'adhésion au club:', clubId);
		goto(`/clubs/${clubId}/join`);
	}
</script>

<svelte:head>
	<title>Rejoindre un club - GestAsso</title>
	<meta name="description" content="Trouvez et rejoignez un club sportif près de chez vous" />
</svelte:head>

<ProtectedRoute requiredRole="member">
	{#snippet children()}
		<div class="min-h-screen bg-gray-50 py-8">
			<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
				<div class="text-center mb-8">
					<h1 class="text-3xl font-bold text-gray-900 mb-4">Rejoindre un club</h1>
					<p class="text-lg text-gray-600">
						Trouvez le club sportif qui vous correspond et commencez votre aventure
					</p>
				</div>

				<!-- Formulaire de recherche -->
				<div class="bg-white rounded-lg shadow-sm border p-6 mb-8">
					<form onsubmit={handleSearch} class="flex gap-4">
						<div class="flex-1">
							<input
								type="text"
								bind:value={searchQuery}
								placeholder="Rechercher un club par nom, sport ou localisation..."
								class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
							/>
						</div>
						<button
							type="submit"
							disabled={isSearching}
							class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
						>
							{#if isSearching}
								Recherche...
							{:else}
								Rechercher
							{/if}
						</button>
					</form>
				</div>

				<!-- Résultats de recherche -->
				{#if hasSearched}
					<div class="space-y-4">
						{#if searchResults.length > 0}
							<h2 class="text-xl font-semibold text-gray-900 mb-4">
								{searchResults.length} club{searchResults.length > 1 ? 's' : ''} trouvé{searchResults.length > 1 ? 's' : ''}
							</h2>
							
							{#each searchResults as club}
								<div class="bg-white rounded-lg shadow-sm border p-6">
									<div class="flex justify-between items-start">
										<div class="flex-1">
											<h3 class="text-lg font-semibold text-gray-900 mb-2">
												{club.name}
											</h3>
											<div class="flex items-center space-x-4 text-sm text-gray-600 mb-3">
												<span class="flex items-center">
													🏃 {club.sport}
												</span>
												<span class="flex items-center">
													📍 {club.location}
												</span>
												<span class="flex items-center">
													👥 {club.membersCount} membres
												</span>
											</div>
											<p class="text-gray-700">{club.description}</p>
										</div>
										<button
											onclick={() => joinClub(club.id)}
											class="ml-4 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
										>
											Rejoindre
										</button>
									</div>
								</div>
							{/each}
						{:else}
							<div class="text-center py-12">
								<div class="text-gray-400 text-4xl mb-4">🔍</div>
								<h3 class="text-lg font-medium text-gray-900 mb-2">Aucun club trouvé</h3>
								<p class="text-gray-600">
									Essayez avec d'autres mots-clés ou élargissez votre recherche.
								</p>
							</div>
						{/if}
					</div>
				{:else if !isSearching}
					<!-- État initial -->
					<div class="text-center py-12">
						<div class="text-gray-400 text-4xl mb-4">⚽</div>
						<h3 class="text-lg font-medium text-gray-900 mb-2">Trouvez votre club</h3>
						<p class="text-gray-600">
							Utilisez la barre de recherche pour trouver des clubs sportifs près de chez vous.
						</p>
					</div>
				{/if}

				<!-- Section d'aide -->
				<div class="mt-12 bg-blue-50 rounded-lg p-6">
					<h3 class="text-lg font-semibold text-blue-900 mb-3">Besoin d'aide ?</h3>
					<div class="grid md:grid-cols-2 gap-4 text-sm text-blue-800">
						<div>
							<h4 class="font-medium mb-1">Comment rechercher un club</h4>
							<p>Tapez le nom du club, le sport pratiqué ou votre ville pour trouver les clubs correspondants.</p>
						</div>
						<div>
							<h4 class="font-medium mb-1">Rejoindre un club</h4>
							<p>Cliquez sur "Rejoindre" pour envoyer une demande d'adhésion au club de votre choix.</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	{/snippet}
</ProtectedRoute> 
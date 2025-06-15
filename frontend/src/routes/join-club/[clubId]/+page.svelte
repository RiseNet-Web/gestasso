<script lang="ts">
	import { page } from '$app/stores';
	import { clubService } from '$lib/services/clubs';
	import type { Club, Team } from '$lib/types/entities';
	import { ArrowLeft, MapPin, Users, Trophy, Calendar, ChevronRight, Loader2 } from '@lucide/svelte';
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	
	let club: Club | null = null;
	let loading = true;
	let error = '';
	
	async function loadClub() {
		const clubId = $page.params.clubId;
		
		if (!clubId) {
			error = 'ID du club manquant';
			loading = false;
			return;
		}

		try {
			const response = await clubService.getClubById(clubId);
			
			if (response.success && response.data) {
				club = response.data;
			} else {
				error = response.error || 'Club non trouvé';
			}
		} catch (err) {
			error = 'Erreur de connexion au serveur';
			console.error('Erreur lors du chargement du club:', err);
		} finally {
			loading = false;
		}
	}
	
	function goBack() {
		goto('/join-club');
	}
	
	function requestToJoinTeam(teamId: string) {
		if (club) {
			goto(`/join-club/${club.id}/request/${teamId}`);
		}
	}

	onMount(() => {
		loadClub();
	});
</script>

<svelte:head>
	<title>{club ? `${club.name} - Rejoindre` : 'Club'} - GestAsso</title>
	<meta name="description" content={club ? `Découvrez ${club.name} et ses équipes. Rejoignez un club de ${club.sport} à ${club.location}.` : 'Détails du club sportif'} />
</svelte:head>

{#if loading}
	<div class="min-h-screen flex items-center justify-center">
		<div class="flex items-center">
			<Loader2 class="h-8 w-8 animate-spin text-sport-blue-600 mr-3" />
			<span class="text-gray-600">Chargement du club...</span>
		</div>
	</div>
{:else if error || !club}
	<div class="min-h-screen flex items-center justify-center">
		<div class="text-center">
			<div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
				<Trophy class="h-8 w-8 text-gray-400" />
			</div>
			<h2 class="text-2xl font-semibold text-gray-900 mb-2">Club non trouvé</h2>
			<p class="text-gray-600 mb-4">Le club que vous recherchez n'existe pas ou n'est pas accessible.</p>
			<button onclick={goBack} class="btn-primary">
				<ArrowLeft size={20} />
				Retour à la recherche
			</button>
		</div>
	</div>
{:else}
	<div class="bg-white">
		<!-- Header avec navigation -->
		<div class="bg-gradient-to-r from-sport-blue-600 to-sport-blue-700 text-white">
			<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
				<button 
					onclick={goBack}
					class="flex items-center text-blue-100 hover:text-white mb-6 transition-colors"
				>
					<ArrowLeft size={20} class="mr-2" />
					Retour à la recherche
				</button>
				
				<div class="flex flex-col lg:flex-row lg:items-start lg:justify-between">
					<!-- Informations principales -->
					<div class="flex-1">
						<div class="flex items-start gap-4 mb-4">
							<!-- Logo du club -->
							<div class="w-20 h-20 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0 overflow-hidden">
								{#if club.image}
									<img
										src={club.image}
										alt={club.name}
										class="w-full h-full object-cover"
									/>
								{:else}
									<span class="text-3xl font-bold text-white">
										{club.sport.charAt(0)}
									</span>
								{/if}
							</div>
							
							<div>
								<h1 class="text-3xl md:text-4xl font-bold mb-2">{club.name}</h1>
								<div class="flex items-center text-blue-100 mb-2">
									<MapPin size={18} class="mr-2" />
									<span class="text-lg">{club.location}</span>
								</div>
								<span class="inline-block bg-white/20 text-white px-3 py-1 rounded-full text-sm font-medium">
									{club.sport}
								</span>
							</div>
						</div>
					</div>
					
					<!-- Statistiques -->
					<div class="grid grid-cols-2 gap-4 lg:gap-6 mt-6 lg:mt-0">
						<div class="text-center">
							<div class="text-2xl md:text-3xl font-bold text-white">{club.membersCount}</div>
							<div class="text-blue-100 text-sm">Membres</div>
						</div>
						<div class="text-center">
							<div class="text-2xl md:text-3xl font-bold text-white">{club.teamsCount}</div>
							<div class="text-blue-100 text-sm">Équipes</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Contenu principal -->
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
			<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
				<!-- Description du club -->
				<div class="lg:col-span-2">
					<div class="card">
						<h2 class="text-2xl font-semibold text-gray-900 mb-4">À propos du club</h2>
						<p class="text-gray-700 leading-relaxed mb-6">
							{club.description}
						</p>
						
						<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
							<div class="flex items-center p-3 bg-gray-50 rounded-lg">
								<Users class="h-5 w-5 text-sport-blue-600 mr-3" />
								<div>
									<div class="font-medium text-gray-900">{club.membersCount} membres</div>
									<div class="text-sm text-gray-600">Communauté active</div>
								</div>
							</div>
							
							<div class="flex items-center p-3 bg-gray-50 rounded-lg">
								<Trophy class="h-5 w-5 text-sport-green-600 mr-3" />
								<div>
									<div class="font-medium text-gray-900">{club.teamsCount} équipes</div>
									<div class="text-sm text-gray-600">Différents niveaux</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Statut des inscriptions -->
				<div class="lg:col-span-1">
					<div class="card">
						<h3 class="text-lg font-semibold text-gray-900 mb-4">Statut des inscriptions</h3>
						
						{#if club.allowRegistrations}
							<div class="flex items-center p-3 bg-green-50 rounded-lg mb-4">
								<div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
								<div>
									<div class="font-medium text-green-800">Inscriptions ouvertes</div>
									<div class="text-sm text-green-600">Vous pouvez postuler</div>
								</div>
							</div>
						{:else}
							<div class="flex items-center p-3 bg-red-50 rounded-lg mb-4">
								<div class="w-3 h-3 bg-red-500 rounded-full mr-3"></div>
								<div>
									<div class="font-medium text-red-800">Inscriptions fermées</div>
									<div class="text-sm text-red-600">Contactez le club directement</div>
								</div>
							</div>
						{/if}
						
						<div class="text-sm text-gray-600">
							<p class="mb-2">Pour rejoindre ce club :</p>
							<ul class="space-y-1 text-xs">
								<li>• Sélectionnez une équipe</li>
								<li>• Remplissez votre demande</li>
								<li>• Attendez la réponse du club</li>
							</ul>
						</div>
					</div>
				</div>
			</div>

			<!-- Liste des équipes -->
			<div class="mt-12">
				<div class="flex items-center justify-between mb-8">
					<h2 class="text-2xl font-semibold text-gray-900">
						Équipes disponibles ({club.teams.length})
					</h2>
				</div>

				{#if club.teams.length > 0}
					<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
						{#each club.teams as team}
							<div class="card hover:shadow-lg transition-shadow duration-300">
								<div class="flex items-start justify-between mb-4">
									<div class="flex-1">
										<h3 class="text-xl font-semibold text-gray-900 mb-2">
											{team.name}
										</h3>
										<div class="space-y-1">
											<div class="flex items-center text-gray-600">
												<Trophy size={16} class="mr-2" />
												<span class="text-sm">{team.category}</span>
											</div>
											<div class="flex items-center text-gray-600">
												<Calendar size={16} class="mr-2" />
												<span class="text-sm">Niveau : {team.level}</span>
											</div>
											<div class="flex items-center text-gray-600">
												<Users size={16} class="mr-2" />
												<span class="text-sm">{team.membersCount} membres</span>
											</div>
										</div>
									</div>
									
									<div class="text-right">
										<span class="inline-block bg-sport-blue-50 text-sport-blue-700 px-2 py-1 rounded text-xs font-medium">
											{team.category}
										</span>
									</div>
								</div>

								{#if club.allowRegistrations}
									<button
										onclick={() => requestToJoinTeam(team.id)}
										class="btn-primary w-full justify-center"
									>
										<span>Demander à rejoindre</span>
										<ChevronRight size={16} />
									</button>
								{:else}
									<div class="w-full text-center py-2 px-4 bg-gray-100 text-gray-500 rounded-lg text-sm">
										Inscriptions fermées
									</div>
								{/if}
							</div>
						{/each}
					</div>
				{:else}
					<div class="text-center py-12">
						<div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
							<Users class="h-8 w-8 text-gray-400" />
						</div>
						<h3 class="text-lg font-medium text-gray-900 mb-2">
							Aucune équipe disponible
						</h3>
						<p class="text-gray-600">
							Ce club n'a pas encore créé d'équipes ou elles ne sont pas ouvertes aux inscriptions.
						</p>
					</div>
				{/if}
			</div>
		</div>
	</div>
{/if} 
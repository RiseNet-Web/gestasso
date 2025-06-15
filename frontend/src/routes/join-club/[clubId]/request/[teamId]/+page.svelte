<script lang="ts">
	import { page } from '$app/stores';
	import { clubService } from '$lib/services/clubs';
	import type { Club, Team, JoinTeamRequest } from '$lib/types/entities';
	import { ArrowLeft, User, Calendar, Trophy, Send, CheckCircle, Loader2 } from '@lucide/svelte';
	import { goto } from '$app/navigation';
	import { auth } from '$lib/stores/auth.svelte';
	import { onMount } from 'svelte';
	
	let club: Club | null = null;
	let team: Team | null = null;
	let loading = true;
	let isSubmitting = false;
	let isSubmitted = false;
	let error = '';
	
	let formData = {
		motivation: '',
		experience: '',
		position: '',
		age: '',
		availability: '',
		additionalInfo: ''
	};
	
	let errors: Record<string, string> = {};
	
	async function loadClubAndTeam() {
		const clubId = $page.params.clubId;
		const teamId = $page.params.teamId;
		
		if (!clubId || !teamId) {
			error = 'Paramètres manquants';
			loading = false;
			return;
		}

		try {
			const response = await clubService.getClubById(clubId);
			
			if (response.success && response.data) {
				club = response.data;
				team = club.teams.find(t => t.id === teamId) || null;
				
				if (!team) {
					error = 'Équipe non trouvée';
				}
			} else {
				error = response.error || 'Club non trouvé';
			}
		} catch (err) {
			error = 'Erreur de connexion au serveur';
			console.error('Erreur lors du chargement:', err);
		} finally {
			loading = false;
		}
	}
	
	function goBack() {
		if (club) {
			goto(`/join-club/${club.id}`);
		} else {
			goto('/join-club');
		}
	}
	
	function validateForm(): boolean {
		errors = {};
		
		if (!formData.motivation.trim() || formData.motivation.trim().length < 50) {
			errors.motivation = 'La motivation est requise (minimum 50 caractères)';
		}
		
		if (!formData.experience.trim()) {
			errors.experience = 'L\'expérience est requise';
		}
		
		if (!formData.age || parseInt(formData.age) < 16 || parseInt(formData.age) > 80) {
			errors.age = 'L\'âge doit être entre 16 et 80 ans';
		}
		
		if (!formData.availability.trim()) {
			errors.availability = 'Les disponibilités sont requises';
		}
		
		return Object.keys(errors).length === 0;
	}
	
	async function handleSubmit(event: Event) {
		event.preventDefault();
		
		if (!validateForm() || !club || !team) return;
		
		isSubmitting = true;
		errors.general = '';
		
		try {
			const requestData: JoinTeamRequest = {
				motivation: formData.motivation.trim(),
				experience: formData.experience.trim(),
				position: formData.position.trim() || undefined,
				age: parseInt(formData.age),
				availability: formData.availability.trim()
			};

			const response = await clubService.requestToJoinTeam(club.id, team.id, requestData);
			
			if (response.success) {
				isSubmitted = true;
			} else {
				errors.general = response.error || 'Erreur lors de l\'envoi de la demande';
			}
		} catch (err) {
			errors.general = 'Erreur de connexion au serveur';
			console.error('Erreur lors de l\'envoi:', err);
		} finally {
			isSubmitting = false;
		}
	}
	
	function goToClubList() {
		goto('/join-club');
	}

	onMount(() => {
		loadClubAndTeam();
	});
</script>

<svelte:head>
	<title>{team && club ? `Rejoindre ${team.name} - ${club.name}` : 'Demande d\'adhésion'} - GestAsso</title>
	<meta name="description" content="Formulaire de demande pour rejoindre une équipe sportive" />
</svelte:head>

{#if loading}
	<div class="min-h-screen flex items-center justify-center">
		<div class="flex items-center">
			<Loader2 class="h-8 w-8 animate-spin text-sport-blue-600 mr-3" />
			<span class="text-gray-600">Chargement...</span>
		</div>
	</div>
{:else if error || !club || !team}
	<div class="min-h-screen flex items-center justify-center">
		<div class="text-center">
			<div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
				<Trophy class="h-8 w-8 text-gray-400" />
			</div>
			<h2 class="text-2xl font-semibold text-gray-900 mb-2">
				{error || 'Équipe non trouvée'}
			</h2>
			<p class="text-gray-600 mb-4">
				{error ? 'Une erreur est survenue lors du chargement.' : 'L\'équipe que vous recherchez n\'existe pas.'}
			</p>
			<button onclick={goBack} class="btn-primary">
				<ArrowLeft size={20} />
				Retour
			</button>
		</div>
	</div>
{:else if isSubmitted}
	<!-- Page de confirmation -->
	<div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
		<div class="max-w-md w-full">
			<div class="bg-white rounded-lg shadow-lg p-8 text-center">
				<div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
					<CheckCircle class="h-10 w-10 text-green-600" />
				</div>
				
				<h2 class="text-2xl font-bold text-gray-900 mb-4">
					Demande envoyée !
				</h2>
				
				<p class="text-gray-600 mb-6">
					Votre demande pour rejoindre <strong>{team.name}</strong> du club <strong>{club.name}</strong> 
					a été envoyée avec succès. Vous recevrez une réponse par email dans les prochains jours.
				</p>
				
				<div class="space-y-3">
					<button
						onclick={goToClubList}
						class="btn-primary w-full"
					>
						Rechercher d'autres clubs
					</button>
					<button
						onclick={goBack}
						class="btn-outline w-full"
					>
						Retour au club
					</button>
				</div>
			</div>
		</div>
	</div>
{:else}
	<!-- Formulaire de demande -->
	<div class="bg-gray-50 min-h-screen">
		<!-- Header -->
		<div class="bg-white border-b border-gray-200">
			<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
				<button 
					onclick={goBack}
					class="flex items-center text-gray-500 hover:text-gray-700 mb-4 transition-colors"
				>
					<ArrowLeft size={20} class="mr-2" />
					Retour au club
				</button>
				
				<div class="flex items-center space-x-4">
					<div class="w-12 h-12 bg-sport-blue-100 rounded-lg flex items-center justify-center">
						<span class="text-lg font-bold text-sport-blue-600">
							{club.sport.charAt(0)}
						</span>
					</div>
					<div>
						<h1 class="text-2xl font-bold text-gray-900">
							Demande pour rejoindre {team.name}
						</h1>
						<p class="text-gray-600">{club.name} - {club.location}</p>
					</div>
				</div>
			</div>
		</div>

		<!-- Contenu principal -->
		<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
			<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
				<!-- Formulaire -->
				<div class="lg:col-span-2">
					<div class="bg-white rounded-lg shadow-sm border border-gray-200">
						<div class="p-6 border-b border-gray-200">
							<h2 class="text-xl font-semibold text-gray-900">
								Informations de candidature
							</h2>
							<p class="text-gray-600 mt-1">
								Remplissez ce formulaire pour faire votre demande d'adhésion
							</p>
						</div>

						<form onsubmit={handleSubmit} class="p-6 space-y-6">
							{#if errors.general}
								<div class="bg-red-50 border border-red-200 rounded-md p-3">
									<p class="text-sm text-red-800">{errors.general}</p>
								</div>
							{/if}

							<!-- Motivation -->
							<div>
								<label for="motivation" class="block text-sm font-medium text-gray-700 mb-2">
									Pourquoi souhaitez-vous rejoindre cette équipe ? *
								</label>
								<textarea
									id="motivation"
									bind:value={formData.motivation}
									rows="4"
									class="input-field {errors.motivation ? 'border-red-300' : ''}"
									placeholder="Expliquez votre motivation à rejoindre cette équipe, vos objectifs et ce que vous comptez apporter..."
								></textarea>
								{#if errors.motivation}
									<p class="mt-1 text-sm text-red-600">{errors.motivation}</p>
								{/if}
							</div>

							<!-- Expérience -->
							<div>
								<label for="experience" class="block text-sm font-medium text-gray-700 mb-2">
									Votre expérience dans ce sport *
								</label>
								<select
									id="experience"
									bind:value={formData.experience}
									class="input-field {errors.experience ? 'border-red-300' : ''}"
								>
									<option value="">Sélectionnez votre niveau</option>
									<option value="debutant">Débutant (moins de 1 an)</option>
									<option value="amateur">Amateur (1-3 ans)</option>
									<option value="confirme">Confirmé (3-5 ans)</option>
									<option value="expert">Expert (plus de 5 ans)</option>
									<option value="competition">Compétition/Club précédent</option>
								</select>
								{#if errors.experience}
									<p class="mt-1 text-sm text-red-600">{errors.experience}</p>
								{/if}
							</div>

							<!-- Âge -->
							<div>
								<label for="age" class="block text-sm font-medium text-gray-700 mb-2">
									Votre âge *
								</label>
								<input
									id="age"
									type="number"
									min="16"
									max="80"
									bind:value={formData.age}
									class="input-field {errors.age ? 'border-red-300' : ''}"
									placeholder="25"
								/>
								{#if errors.age}
									<p class="mt-1 text-sm text-red-600">{errors.age}</p>
								{/if}
							</div>

							<!-- Position (optionnel) -->
							<div>
								<label for="position" class="block text-sm font-medium text-gray-700 mb-2">
									Position/Poste préféré (optionnel)
								</label>
								<input
									id="position"
									type="text"
									bind:value={formData.position}
									class="input-field"
									placeholder="Ex: Milieu de terrain, Défenseur, etc."
								/>
							</div>

							<!-- Disponibilités -->
							<div>
								<label for="availability" class="block text-sm font-medium text-gray-700 mb-2">
									Vos disponibilités *
								</label>
								<textarea
									id="availability"
									bind:value={formData.availability}
									rows="3"
									class="input-field {errors.availability ? 'border-red-300' : ''}"
									placeholder="Ex: Mardi et jeudi soir, weekend disponible, etc."
								></textarea>
								{#if errors.availability}
									<p class="mt-1 text-sm text-red-600">{errors.availability}</p>
								{/if}
							</div>

							<!-- Informations supplémentaires -->
							<div>
								<label for="additionalInfo" class="block text-sm font-medium text-gray-700 mb-2">
									Informations supplémentaires (optionnel)
								</label>
								<textarea
									id="additionalInfo"
									bind:value={formData.additionalInfo}
									rows="3"
									class="input-field"
									placeholder="Blessures, contraintes particulières, objectifs personnels..."
								></textarea>
							</div>

							<!-- Bouton de soumission -->
							<div class="pt-4">
								<button
									type="submit"
									disabled={isSubmitting}
									class="btn-primary w-full justify-center disabled:opacity-50 disabled:cursor-not-allowed"
								>
									{#if isSubmitting}
										<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
											<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
											<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
										</svg>
										Envoi en cours...
									{:else}
										<Send size={20} />
										Envoyer ma demande
									{/if}
								</button>
							</div>
						</form>
					</div>
				</div>

				<!-- Sidebar avec infos de l'équipe -->
				<div class="lg:col-span-1">
					<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-24">
						<h3 class="text-lg font-semibold text-gray-900 mb-4">
							Informations de l'équipe
						</h3>
						
						<div class="space-y-4">
							<div>
								<div class="text-sm font-medium text-gray-500">Équipe</div>
								<div class="text-gray-900">{team.name}</div>
							</div>
							
							<div>
								<div class="text-sm font-medium text-gray-500">Catégorie</div>
								<div class="text-gray-900">{team.category}</div>
							</div>
							
							<div>
								<div class="text-sm font-medium text-gray-500">Niveau</div>
								<div class="text-gray-900">{team.level}</div>
							</div>
							
							<div>
								<div class="text-sm font-medium text-gray-500">Membres actuels</div>
								<div class="text-gray-900">{team.membersCount} membres</div>
							</div>
							
							<div>
								<div class="text-sm font-medium text-gray-500">Club</div>
								<div class="text-gray-900">{club.name}</div>
							</div>
							
							<div>
								<div class="text-sm font-medium text-gray-500">Localisation</div>
								<div class="text-gray-900">{club.location}</div>
							</div>
						</div>

						{#if auth.user}
							<div class="mt-6 pt-4 border-t border-gray-200">
								<div class="text-sm font-medium text-gray-500 mb-1">Candidat</div>
								<div class="text-gray-900">{auth.user.firstName} {auth.user.lastName}</div>
								<div class="text-sm text-gray-600">{auth.user.email}</div>
							</div>
						{/if}
					</div>
				</div>
			</div>
		</div>
	</div>
{/if} 
<script lang="ts">
	import { Users, Trophy, Target, ArrowRight, CheckCircle, FileText, Shield, Clock } from '@lucide/svelte';
	import { fly, fade } from 'svelte/transition';
	import { onMount } from 'svelte';
	
	let mounted = false;
	
	onMount(() => {
		mounted = true;
	});
</script>

<svelte:head>
	<title>GestAsso - Plateforme de gestion pour clubs sportifs</title>
	<meta name="description" content="Créez ou rejoignez un club sportif associatif. Gérez facilement vos équipes, membres et événements avec GestAsso." />
</svelte:head>

<!-- Hero Section -->
<section class="bg-gradient-to-br from-sport-blue-600 to-sport-blue-800 text-white">
	<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
		<div class="text-center">
			{#if mounted}
				<h1 
					class="text-4xl md:text-6xl font-bold mb-6"
					in:fly={{ y: 20, duration: 800, delay: 200 }}
				>
					Simplifiez la gestion documentaire
					<span class="text-sport-green-400">de votre club sportif</span>
				</h1>
				<p 
					class="text-xl md:text-2xl mb-8 text-blue-100 max-w-3xl mx-auto"
					in:fly={{ y: 20, duration: 800, delay: 400 }}
				>
					Responsabilisez vos membres et libérez votre staff associatif grâce à une plateforme 
					moderne de gestion documentaire. Fini les documents perdus et les relances incessantes !
				</p>
				<div 
					class="flex flex-col sm:flex-row gap-4 justify-center"
					in:fly={{ y: 20, duration: 800, delay: 600 }}
				>
					<a href="/register?role=member" class="btn-secondary text-lg px-8 py-4 inline-flex items-center space-x-2">
						<Users size={24} />
						<span>Rejoindre un club</span>
						<ArrowRight size={20} />
					</a>
					<a href="/register?role=owner" class="btn-outline border-white text-white hover:bg-white hover:text-sport-blue-600 text-lg px-8 py-4 inline-flex items-center space-x-2">
						<Trophy size={24} />
						<span>Créer mon club</span>
						<ArrowRight size={20} />
					</a>
				</div>
			{/if}
		</div>
	</div>
</section>

<!-- Section Problématiques -->
<section class="py-20 bg-white">
	<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
		{#if mounted}
			<div class="text-center mb-16" in:fade={{ duration: 800, delay: 800 }}>
				<h2 class="text-3xl md:text-4xl font-bold mb-6 text-gray-900">
					Les défis du staff associatif
				</h2>
				<p class="text-xl text-gray-600 max-w-4xl mx-auto">
					Gérer un club sportif associatif implique de nombreuses tâches administratives. 
					Notre plateforme transforme ces contraintes en opportunités de responsabilisation.
				</p>
			</div>
			
			<div class="grid grid-cols-1 md:grid-cols-3 gap-8" in:fly={{ y: 30, duration: 800, delay: 1000 }}>
				<div class="text-center">
					<div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
						<FileText class="text-red-600" size={32} />
					</div>
					<h3 class="text-xl font-semibold mb-3 text-gray-900">Documents dispersés</h3>
					<p class="text-gray-600">
						Certificats médicaux, autorisations parentales, licences... Les documents s'accumulent et se perdent facilement.
					</p>
				</div>
				
				<div class="text-center">
					<div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
						<Clock class="text-orange-600" size={32} />
					</div>
					<h3 class="text-xl font-semibold mb-3 text-gray-900">Relances multiples</h3>
					<p class="text-gray-600">
						Le staff perd un temps précieux à relancer les membres pour obtenir les documents manquants.
					</p>
				</div>
				
				<div class="text-center">
					<div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
						<Shield class="text-yellow-600" size={32} />
					</div>
					<h3 class="text-xl font-semibold mb-3 text-gray-900">Conformité complexe</h3>
					<p class="text-gray-600">
						Assurer la conformité réglementaire devient un casse-tête sans système organisé.
					</p>
				</div>
			</div>
		{/if}
	</div>
</section>

<!-- Section Solutions -->
<section class="py-20 bg-gray-50">
	<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
		{#if mounted}
			<div class="text-center mb-16" in:fade={{ duration: 800, delay: 1200 }}>
				<h2 class="text-3xl md:text-4xl font-bold mb-6 text-gray-900">
					Notre solution : la responsabilisation intelligente
				</h2>
				<p class="text-xl text-gray-600">
					Transformez vos membres en acteurs de la gestion documentaire et libérez votre temps pour ce qui compte vraiment.
				</p>
			</div>
			
			<div class="grid grid-cols-1 md:grid-cols-3 gap-8" in:fly={{ y: 30, duration: 800, delay: 1400 }}>
				<div class="text-center">
					<div class="w-16 h-16 bg-sport-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
						<Users class="text-sport-green-600" size={32} />
					</div>
					<h3 class="text-xl font-semibold mb-3 text-gray-900">Membres autonomes</h3>
					<p class="text-gray-600">
						Chaque membre gère ses propres documents via une interface intuitive et reçoit des rappels automatiques.
					</p>
				</div>
				
				<div class="text-center">
					<div class="w-16 h-16 bg-sport-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
						<Target class="text-sport-blue-600" size={32} />
					</div>
					<h3 class="text-xl font-semibold mb-3 text-gray-900">Suivi centralisé</h3>
					<p class="text-gray-600">
						Le staff visualise en temps réel l'état des documents de tous les membres sur un tableau de bord unifié.
					</p>
				</div>
				
				<div class="text-center">
					<div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
						<Shield class="text-purple-600" size={32} />
					</div>
					<h3 class="text-xl font-semibold mb-3 text-gray-900">Conformité assurée</h3>
					<p class="text-gray-600">
						Validation automatique des documents, alertes d'expiration et respect des obligations légales.
					</p>
				</div>
			</div>
		{/if}
	</div>
</section>

<!-- Section Parcours Utilisateurs -->
<section class="py-20 bg-white">
	<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
		{#if mounted}
			<div class="text-center mb-16" in:fade={{ duration: 800, delay: 1600 }}>
				<h2 class="text-3xl md:text-4xl font-bold mb-6 text-gray-900">
					Deux rôles, une même efficacité
				</h2>
				<p class="text-xl text-gray-600">
					Que vous soyez membre ou dirigeant, notre plateforme s'adapte à vos besoins spécifiques.
				</p>
			</div>
			
			<div class="grid grid-cols-1 lg:grid-cols-2 gap-8" in:fly={{ y: 30, duration: 800, delay: 1800 }}>
				<!-- Parcours Membre -->
				<div class="card hover:shadow-lg transition-shadow duration-300">
					<div class="flex items-center space-x-3 mb-6">
						<div class="w-12 h-12 bg-sport-green-100 rounded-lg flex items-center justify-center">
							<Users class="text-sport-green-600" size={24} />
						</div>
						<h3 class="text-2xl font-bold text-gray-900">Je suis membre</h3>
					</div>
					
					<p class="text-gray-600 mb-6">
						Rejoignez votre club et gérez facilement tous vos documents en toute autonomie.
					</p>
					
					<ul class="space-y-3 mb-8">
						<li class="flex items-center space-x-3">
							<CheckCircle class="text-sport-green-500" size={20} />
							<span class="text-gray-700">Téléchargez vos documents en quelques clics</span>
						</li>
						<li class="flex items-center space-x-3">
							<CheckCircle class="text-sport-green-500" size={20} />
							<span class="text-gray-700">Recevez des rappels automatiques</span>
						</li>
						<li class="flex items-center space-x-3">
							<CheckCircle class="text-sport-green-500" size={20} />
							<span class="text-gray-700">Suivez l'état de validation de vos documents</span>
						</li>
					</ul>
					
					<a href="/register?role=member" class="btn-secondary w-full justify-center inline-flex items-center space-x-2">
						<Users size={20} />
						<span>Rejoindre mon club</span>
						<ArrowRight size={20} />
					</a>
				</div>
				
				<!-- Parcours Staff/Owner -->
				<div class="card hover:shadow-lg transition-shadow duration-300">
					<div class="flex items-center space-x-3 mb-6">
						<div class="w-12 h-12 bg-sport-blue-100 rounded-lg flex items-center justify-center">
							<Trophy class="text-sport-blue-600" size={24} />
						</div>
						<h3 class="text-2xl font-bold text-gray-900">Je dirige un club</h3>
					</div>
					
					<p class="text-gray-600 mb-6">
						Créez votre club et bénéficiez d'outils puissants pour simplifier votre gestion administrative.
					</p>
					
					<ul class="space-y-3 mb-8">
						<li class="flex items-center space-x-3">
							<CheckCircle class="text-sport-blue-500" size={20} />
							<span class="text-gray-700">Tableau de bord des documents membres</span>
						</li>
						<li class="flex items-center space-x-3">
							<CheckCircle class="text-sport-blue-500" size={20} />
							<span class="text-gray-700">Validation et archivage automatisés</span>
						</li>
						<li class="flex items-center space-x-3">
							<CheckCircle class="text-sport-blue-500" size={20} />
							<span class="text-gray-700">Rapports de conformité en un clic</span>
						</li>
					</ul>
					
					<a href="/register?role=owner" class="btn-primary w-full justify-center inline-flex items-center space-x-2">
						<Trophy size={20} />
						<span>Créer mon club</span>
						<ArrowRight size={20} />
					</a>
				</div>
			</div>
		{/if}
	</div>
</section>

<!-- Section CTA Final -->
<section class="py-20 bg-sport-blue-600 text-white">
	<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
		{#if mounted}
			<div in:fade={{ duration: 800, delay: 2000 }}>
				<h2 class="text-3xl md:text-4xl font-bold mb-6">
					Prêt à révolutionner votre gestion documentaire ?
				</h2>
				<p class="text-xl mb-8 text-blue-100">
					Rejoignez les clubs qui ont choisi l'efficacité et la responsabilisation de leurs membres.
				</p>
				<div class="flex flex-col sm:flex-row gap-4 justify-center">
					<a href="/register" class="btn-secondary text-lg px-8 py-4">
						Démarrer gratuitement
					</a>
					<a href="/login" class="btn-outline border-white text-white hover:bg-white hover:text-sport-blue-600 text-lg px-8 py-4">
						Se connecter
					</a>
				</div>
			</div>
		{/if}
	</div>
</section>

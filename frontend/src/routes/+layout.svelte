<script lang="ts">
	import '../app.css';
	import Header from '$lib/components/Header.svelte';
	import Footer from '$lib/components/Footer.svelte';

	// Données du serveur
	export let data;

	// L'utilisateur est maintenant disponible via data.user
	// Plus besoin d'initialisation côté client
</script>

<svelte:head>
	<title>{data.seo.title}</title>
	<meta name="description" content={data.seo.description} />
	<meta name="keywords" content={data.seo.keywords} />
	
	<!-- Open Graph / Facebook -->
	<meta property="og:type" content="website" />
	<meta property="og:url" content={data.seo.canonical} />
	<meta property="og:title" content={data.seo.ogTitle} />
	<meta property="og:description" content={data.seo.ogDescription} />
	<meta property="og:image" content={data.seo.ogImage} />
	
	<!-- Twitter -->
	<meta property="twitter:card" content="summary_large_image" />
	<meta property="twitter:url" content={data.seo.canonical} />
	<meta property="twitter:title" content={data.seo.ogTitle} />
	<meta property="twitter:description" content={data.seo.ogDescription} />
	<meta property="twitter:image" content={data.seo.ogImage} />
	
	<!-- Canonical URL -->
	<link rel="canonical" href={data.seo.canonical} />
	
	<!-- Additional SEO -->
	<meta name="robots" content="index, follow" />
	<meta name="author" content="GestAsso" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
</svelte:head>

<div class="min-h-screen flex flex-col">
	<Header />
	<main class="flex-1">
		<slot />
	</main>
	<Footer />
</div> 
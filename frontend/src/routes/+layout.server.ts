import type { LayoutServerLoad } from './$types';

export const load: LayoutServerLoad = async ({ url, locals }) => {
	// Métadonnées SEO par défaut pour toute l'application
	const defaultSeo = {
		title: 'GestAsso - Plateforme de gestion documentaire pour clubs sportifs',
		description: 'Simplifiez la gestion documentaire de votre club sportif associatif. Responsabilisez vos membres grâce à une plateforme moderne de communication et partage de documents.',
		keywords: 'gestion documentaire, club sportif, association, documents, membres, staff associatif',
		ogTitle: 'GestAsso - Gestion documentaire pour clubs sportifs',
		ogDescription: 'La plateforme qui révolutionne la gestion documentaire des clubs sportifs associatifs',
		ogImage: '/images/og-default.jpg',
		canonical: url.href
	};

	// SEO spécifique par page
	const pageSeo = getPageSeo(url.pathname);
	
	return {
		seo: {
			...defaultSeo,
			...pageSeo,
			canonical: url.href
		},
		// Données globales disponibles sur toutes les pages
		currentPath: url.pathname,
		timestamp: new Date().toISOString(),
		user: locals.user
	};
};

function getPageSeo(pathname: string) {
	const routes: Record<string, any> = {
		'/': {
			title: 'GestAsso - Plateforme de gestion documentaire pour clubs sportifs',
			description: 'Simplifiez la gestion documentaire de votre club sportif. Responsabilisez vos membres et facilitez le travail du staff associatif avec notre plateforme moderne.',
		},
		'/join-club': {
			title: 'Rejoindre un club - GestAsso',
			description: 'Trouvez et rejoignez un club sportif associatif. Accédez facilement aux documents de votre équipe et restez informé des actualités.',
		},
		'/create-club': {
			title: 'Créer un club - GestAsso',
			description: 'Créez votre club sportif associatif et bénéficiez d\'outils modernes de gestion documentaire pour simplifier l\'administration.',
		},
		'/login': {
			title: 'Connexion - GestAsso',
			description: 'Connectez-vous à votre espace GestAsso pour accéder aux documents de votre club et gérer vos informations.',
		},
		'/register': {
			title: 'Créer un compte - GestAsso',
			description: 'Créez votre compte GestAsso gratuit pour rejoindre votre club et accéder à la gestion documentaire simplifiée.',
		}
	};

	return routes[pathname] || {};
} 
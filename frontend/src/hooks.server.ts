import { redirect } from '@sveltejs/kit';
import { dev } from '$app/environment';
import { API_BASE_URL } from '$lib/config/env';
import type { Handle } from '@sveltejs/kit';
import type { User } from '$lib/types/entities';

export const handle: Handle = async ({ event, resolve }) => {
	// Récupérer les tokens depuis les cookies
	const accessToken = event.cookies.get('auth-token');
	const refreshToken = event.cookies.get('refresh-token');

	// Initialiser les données utilisateur
	event.locals.user = null;
	event.locals.accessToken = null;
	event.locals.refreshToken = null;

	// Si on a un token d'accès, vérifier l'utilisateur
	if (accessToken && refreshToken) {
		try {
			// Vérifier le token en récupérant le profil utilisateur
			const response = await fetch(`${API_BASE_URL}/profile`, {
				method: 'GET',
				headers: {
					'Authorization': `Bearer ${accessToken}`,
					'Accept': 'application/json'
				}
			});

			if (response.ok) {
				const user: User = await response.json();
				
				// Stocker les informations utilisateur dans locals
				event.locals.user = user;
				event.locals.accessToken = accessToken;
				event.locals.refreshToken = refreshToken;
			} else if (response.status === 401) {
				// Token expiré, essayer de le rafraîchir
				try {
					const refreshResponse = await fetch(`${API_BASE_URL}/refresh-token`, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json'
						},
						body: JSON.stringify({ refreshToken })
					});

					if (refreshResponse.ok) {
						const refreshData = await refreshResponse.json();
						
						if (refreshData.accessToken && refreshData.refreshToken) {
							// Mettre à jour les cookies avec les nouveaux tokens
							event.cookies.set('auth-token', refreshData.accessToken, {
								path: '/',
								httpOnly: true,
								secure: !dev, // Sécurisé en production seulement
								sameSite: 'strict',
								maxAge: 60 * 60 * 24 * 7 // 7 jours
							});

							event.cookies.set('refresh-token', refreshData.refreshToken, {
								path: '/',
								httpOnly: true,
								secure: !dev, // Sécurisé en production seulement
								sameSite: 'strict',
								maxAge: 60 * 60 * 24 * 30 // 30 jours
							});

							// Récupérer le profil avec le nouveau token
							const profileResponse = await fetch(`${API_BASE_URL}/profile`, {
								method: 'GET',
								headers: {
									'Authorization': `Bearer ${refreshData.accessToken}`,
									'Accept': 'application/json'
								}
							});

							if (profileResponse.ok) {
								const user: User = await profileResponse.json();
								event.locals.user = user;
								event.locals.accessToken = refreshData.accessToken;
								event.locals.refreshToken = refreshData.refreshToken;
							}
						}
					} else {
						// Refresh échoué, nettoyer les cookies
						event.cookies.delete('auth-token', { path: '/' });
						event.cookies.delete('refresh-token', { path: '/' });
					}
				} catch (refreshError) {
					console.warn('Erreur lors du refresh du token:', refreshError);
					// Nettoyer les cookies en cas d'erreur
					event.cookies.delete('auth-token', { path: '/' });
					event.cookies.delete('refresh-token', { path: '/' });
				}
			}
		} catch (error) {
			console.warn('Erreur lors de la vérification du token:', error);
			// En cas d'erreur, nettoyer les cookies
			event.cookies.delete('auth-token', { path: '/' });
			event.cookies.delete('refresh-token', { path: '/' });
		}
	}

	// Vérifier les routes protégées
	const url = new URL(event.request.url);
	const isProtectedRoute = [
		'/create-club',
		'/join-club',
		'/profile',
		'/dashboard'
	].some(route => url.pathname.startsWith(route));

	// Rediriger vers login si route protégée et pas connecté
	if (isProtectedRoute && !event.locals.user) {
		throw redirect(302, `/login?redirect=${encodeURIComponent(url.pathname + url.search)}`);
	}

	// Vérifier les permissions spécifiques
	if (url.pathname.startsWith('/create-club') && event.locals.user) {
		const isOwner = event.locals.user.roles?.includes('ROLE_CLUB_OWNER') || 
		                event.locals.user.onboardingType === 'owner';
		
		if (!isOwner) {
			throw redirect(302, '/join-club');
		}
	}

	if (url.pathname.startsWith('/join-club') && event.locals.user) {
		const isMember = event.locals.user.roles?.includes('ROLE_USER') || 
		                 event.locals.user.onboardingType === 'member';
		
		if (!isMember) {
			throw redirect(302, '/create-club');
		}
	}

	// Continuer avec la résolution de la requête
	const response = await resolve(event);
	return response;
}; 
import { redirect } from '@sveltejs/kit';
import { API_BASE_URL } from '$lib/config/env';

export async function load({ cookies }: { cookies: any }) {
	// Récupérer le refresh token
	const refreshToken = cookies.get('refresh-token');

	// Appeler l'API de déconnexion si on a un refresh token
	if (refreshToken) {
		try {
			await fetch(`${API_BASE_URL}/logout`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'application/json'
				},
				body: JSON.stringify({ refreshToken })
			});
		} catch (error) {
			console.warn('Erreur lors de la déconnexion API:', error);
		}
	}

	// Nettoyer les cookies
	cookies.delete('auth-token', { path: '/' });
	cookies.delete('refresh-token', { path: '/' });

	// Rediriger vers la page de connexion
	throw redirect(302, '/login');
} 
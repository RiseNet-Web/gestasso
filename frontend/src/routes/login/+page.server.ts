import { fail, redirect } from '@sveltejs/kit';
import { dev } from '$app/environment';
import { API_BASE_URL } from '$lib/config/env';
import type { LoginRequest, LoginResponse } from '$lib/types/entities';

export async function load({ cookies }: { cookies: any }) {
	// Vérifier si l'utilisateur est déjà connecté
	const token = cookies.get('auth-token');
	if (token) {
		// Rediriger vers la page d'accueil si déjà connecté
		throw redirect(302, '/');
	}

	return {};
}

export const actions = {
	default: async ({ request, cookies, url }: { request: Request; cookies: any; url: URL }) => {
		const data = await request.formData();
		
		// Extraire les données du formulaire
		const email = data.get('email')?.toString();
		const password = data.get('password')?.toString();

		// Validation côté serveur
		const errors: Record<string, string> = {};

		if (!email || !email.trim()) {
			errors.email = 'Email requis';
		}

		if (!password || !password.trim()) {
			errors.password = 'Mot de passe requis';
		}

		// Retourner les erreurs si validation échoue
		if (Object.keys(errors).length > 0) {
			return fail(400, {
				errors,
				formData: { email }
			});
		}

		// Préparer les données pour l'API
		const loginData: LoginRequest = {
			email: email!.trim(),
			password: password!
		};

		try {
			// Appel à l'API backend
			const response = await fetch(`${API_BASE_URL}/login`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'application/json'
				},
				body: JSON.stringify(loginData)
			});

			if (!response.ok) {
				const errorData = await response.json().catch(() => ({}));
				
				// Gestion des erreurs spécifiques de l'API
				if (response.status === 401) {
					return fail(401, {
						errors: { general: 'Email ou mot de passe incorrect' },
						formData: { email }
					});
				}

				if (response.status === 400 && errorData.errors) {
					// Erreurs de validation de l'API
					const apiErrors: Record<string, string> = {};
					
					if (Array.isArray(errorData.errors)) {
						errorData.errors.forEach((error: string) => {
							if (error.includes('email')) {
								apiErrors.email = error;
							} else if (error.includes('password')) {
								apiErrors.password = error;
							} else {
								apiErrors.general = error;
							}
						});
					}
					
					return fail(400, {
						errors: apiErrors,
						formData: { email }
					});
				}

				// Autres erreurs
				return fail(response.status, {
					errors: { 
						general: errorData.error || errorData.message || 'Erreur lors de la connexion' 
					},
					formData: { email }
				});
			}

			const result: LoginResponse = await response.json();

			if (result.accessToken && result.refreshToken) {
				// Stocker les tokens dans les cookies sécurisés
				cookies.set('auth-token', result.accessToken, {
					path: '/',
					httpOnly: true,
					secure: !dev, // Sécurisé en production seulement
					sameSite: 'strict',
					maxAge: 60 * 60 * 24 * 7 // 7 jours
				});

				cookies.set('refresh-token', result.refreshToken, {
					path: '/',
					httpOnly: true,
					secure: !dev, // Sécurisé en production seulement
					sameSite: 'strict',
					maxAge: 60 * 60 * 24 * 30 // 30 jours
				});

				// Redirection après connexion réussie
				const redirectUrl = url.searchParams.get('redirect') || '/';
				throw redirect(302, redirectUrl);
			}

			// Si pas de tokens dans la réponse
			return fail(500, {
				errors: { general: 'Réponse invalide du serveur' },
				formData: { email }
			});

		} catch (error) {
			console.error('Erreur de connexion:', error);
			
			return fail(500, {
				errors: { general: 'Erreur de connexion au serveur' },
				formData: { email }
			});
		}
	}
}; 
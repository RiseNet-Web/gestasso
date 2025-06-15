import { fail, redirect } from '@sveltejs/kit';
import { dev } from '$app/environment';
import { API_BASE_URL } from '$lib/config/env';
import type { RegisterRequest, RegisterResponse } from '$lib/types/entities';

export async function load({ url, cookies }: { url: URL; cookies: any }) {
	// Vérifier si l'utilisateur est déjà connecté
	const token = cookies.get('auth-token');
	if (token) {
		// Rediriger vers la page d'accueil si déjà connecté
		throw redirect(302, '/');
	}

	// Récupérer le rôle depuis l'URL
	const role = url.searchParams.get('role');
	const validRole = role === 'owner' || role === 'member' ? role : 'member';

	return {
		role: validRole
	};
}

export const actions = {
	default: async ({ request, cookies, url }: { request: Request; cookies: any; url: URL }) => {
		const data = await request.formData();
		
		// Extraire les données du formulaire
		const email = data.get('email')?.toString();
		const password = data.get('password')?.toString();
		const confirmPassword = data.get('confirmPassword')?.toString();
		const firstName = data.get('firstName')?.toString();
		const lastName = data.get('lastName')?.toString();
		const onboardingType = data.get('onboardingType')?.toString() as 'owner' | 'member';
		const phone = data.get('phone')?.toString();
		const dateOfBirth = data.get('dateOfBirth')?.toString();

		// Validation côté serveur
		const errors: Record<string, string> = {};

		if (!email || !email.includes('@')) {
			errors.email = 'Email valide requis';
		}

		if (!password || password.length < 6) {
			errors.password = 'Mot de passe d\'au moins 6 caractères requis';
		}

		if (password !== confirmPassword) {
			errors.confirmPassword = 'Les mots de passe ne correspondent pas';
		}

		if (!firstName || firstName.trim().length < 2) {
			errors.firstName = 'Prénom d\'au moins 2 caractères requis';
		}

		if (!lastName || lastName.trim().length < 2) {
			errors.lastName = 'Nom d\'au moins 2 caractères requis';
		}

		if (!onboardingType || !['owner', 'member'].includes(onboardingType)) {
			errors.onboardingType = 'Type d\'utilisateur requis';
		}

		if (phone && !/^[0-9+\-\s()]+$/.test(phone)) {
			errors.phone = 'Format de téléphone invalide';
		}

		if (dateOfBirth) {
			const birthDate = new Date(dateOfBirth);
			const now = new Date();
			const age = now.getFullYear() - birthDate.getFullYear();
			
			if (age < 13 || age > 120) {
				errors.dateOfBirth = 'L\'âge doit être entre 13 et 120 ans';
			}
		}

		// Retourner les erreurs si validation échoue
		if (Object.keys(errors).length > 0) {
			return fail(400, {
				errors,
				formData: {
					email,
					firstName,
					lastName,
					onboardingType,
					phone,
					dateOfBirth
				}
			});
		}

		// Préparer les données pour l'API
		const registerData: RegisterRequest = {
			email: email!,
			password: password!,
			firstName: firstName!.trim(),
			lastName: lastName!.trim(),
			onboardingType: onboardingType!,
			phone: phone?.trim() || undefined,
			dateOfBirth: dateOfBirth || undefined
		};

		try {
			// Appel à l'API backend
			const response = await fetch(`${API_BASE_URL}/register`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'application/json'
				},
				body: JSON.stringify(registerData)
			});

			if (!response.ok) {
				const errorData = await response.json().catch(() => ({}));
				
				// Gestion des erreurs spécifiques de l'API
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
						formData: {
							email,
							firstName,
							lastName,
							onboardingType,
							phone,
							dateOfBirth
						}
					});
				}

				// Autres erreurs
				return fail(response.status, {
					errors: { 
						general: errorData.error || errorData.message || 'Erreur lors de l\'inscription' 
					},
					formData: {
						email,
						firstName,
						lastName,
						onboardingType,
						phone,
						dateOfBirth
					}
				});
			}

			const result: RegisterResponse = await response.json();

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

				// Redirection après inscription réussie
				const redirectUrl = url.searchParams.get('redirect') || '/';
				throw redirect(302, redirectUrl);
			}

			// Si pas de tokens dans la réponse
			return fail(500, {
				errors: { general: 'Réponse invalide du serveur' },
				formData: {
					email,
					firstName,
					lastName,
					onboardingType,
					phone,
					dateOfBirth
				}
			});

		} catch (error) {
			console.error('Erreur d\'inscription:', error);
			
			return fail(500, {
				errors: { general: 'Erreur de connexion au serveur' },
				formData: {
					email,
					firstName,
					lastName,
					onboardingType,
					phone,
					dateOfBirth
				}
			});
		}
	}
}
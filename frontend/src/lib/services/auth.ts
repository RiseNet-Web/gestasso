import { apiService } from './api';
import { auth } from '$lib/stores/auth.svelte';
import type { 
	LoginRequest, 
	RegisterRequest, 
	LoginResponse, 
	RegisterResponse,
	User 
} from '$lib/types/entities';

export interface AuthResult {
	success: boolean;
	error?: string;
	errors?: string[];
	user?: User;
}

/**
 * Service d'authentification avec intégration complète
 * Gère l'inscription, la connexion et la synchronisation des tokens
 */
class AuthenticationService {
	
	/**
	 * Inscription d'un nouvel utilisateur
	 * Gère automatiquement la connexion après inscription
	 */
	async register(userData: RegisterRequest): Promise<AuthResult> {
		try {
			// Validation côté client
			const validationError = this.validateRegistrationData(userData);
			if (validationError) {
				return {
					success: false,
					error: validationError
				};
			}

			// Appel API d'inscription
			const response = await apiService.post<RegisterResponse>('/register', userData);

			if (response.success && response.data) {
				const { user, accessToken, refreshToken } = response.data;

				// Configuration automatique du service API
				apiService.setTokens(accessToken, refreshToken);

				// Synchronisation avec le store d'authentification
				await this.syncAuthStore(user, accessToken, refreshToken);

				return {
					success: true,
					user
				};
			} else {
				return {
					success: false,
					error: response.error || 'Erreur lors de l\'inscription',
					errors: response.errors
				};
			}
		} catch (error) {
			console.error('Erreur d\'inscription:', error);
			return {
				success: false,
				error: 'Erreur de connexion au serveur'
			};
		}
	}

	/**
	 * Connexion d'un utilisateur existant
	 */
	async login(email: string, password: string): Promise<AuthResult> {
		try {
			// Validation côté client
			if (!email || !password) {
				return {
					success: false,
					error: 'Email et mot de passe requis'
				};
			}

			const loginData: LoginRequest = { email, password };
			const response = await apiService.post<LoginResponse>('/login', loginData);

			if (response.success && response.data) {
				const { user, accessToken, refreshToken } = response.data;

				// Configuration automatique du service API
				apiService.setTokens(accessToken, refreshToken);

				// Synchronisation avec le store d'authentification
				await this.syncAuthStore(user, accessToken, refreshToken);

				return {
					success: true,
					user
				};
			} else {
				return {
					success: false,
					error: response.error || 'Identifiants invalides',
					errors: response.errors
				};
			}
		} catch (error) {
			console.error('Erreur de connexion:', error);
			return {
				success: false,
				error: 'Erreur de connexion au serveur'
			};
		}
	}

	/**
	 * Déconnexion de l'utilisateur
	 */
	async logout(): Promise<void> {
		try {
			// Appel API de déconnexion
			const refreshToken = localStorage.getItem('refresh-token');
			if (refreshToken) {
				await apiService.post('/logout', { refreshToken });
			}
		} catch (error) {
			console.warn('Erreur lors de la déconnexion API:', error);
		} finally {
			// Nettoyage local (toujours effectué)
			apiService.clearTokens();
			
			// Réinitialiser le store d'authentification
			// Note: Le store gère déjà son propre nettoyage
		}
	}

	/**
	 * Initialisation de l'authentification au démarrage
	 */
	async initialize(): Promise<boolean> {
		try {
			// Initialiser le service API avec les tokens stockés
			apiService.initializeFromStorage();

			const token = localStorage.getItem('auth-token');
			const refreshToken = localStorage.getItem('refresh-token');

			if (!token || !refreshToken) {
				return false;
			}

			// Vérifier la validité en récupérant le profil
			const response = await apiService.get<User>('/profile');

			if (response.success && response.data) {
				// Synchroniser avec le store
				await this.syncAuthStore(response.data, token, refreshToken);
				return true;
			} else {
				// Token invalide, nettoyer
				await this.logout();
				return false;
			}
		} catch (error) {
			console.warn('Erreur lors de l\'initialisation:', error);
			await this.logout();
			return false;
		}
	}

	/**
	 * Mise à jour du profil utilisateur
	 */
	async updateProfile(profileData: Partial<User>): Promise<AuthResult> {
		try {
			const response = await apiService.put<User>('/profile', profileData);

			if (response.success && response.data) {
				// Mettre à jour le store avec les nouvelles données
				// Note: Le store se met à jour automatiquement
				return {
					success: true,
					user: response.data
				};
			} else {
				return {
					success: false,
					error: response.error || 'Erreur lors de la mise à jour',
					errors: response.errors
				};
			}
		} catch (error) {
			console.error('Erreur de mise à jour du profil:', error);
			return {
				success: false,
				error: 'Erreur de connexion au serveur'
			};
		}
	}

	/**
	 * Validation des données d'inscription côté client
	 */
	private validateRegistrationData(userData: RegisterRequest): string | null {
		if (!userData.email || !userData.email.includes('@')) {
			return 'Email valide requis';
		}

		if (!userData.password || userData.password.length < 6) {
			return 'Mot de passe d\'au moins 6 caractères requis';
		}

		if (!userData.firstName || userData.firstName.trim().length < 2) {
			return 'Prénom d\'au moins 2 caractères requis';
		}

		if (!userData.lastName || userData.lastName.trim().length < 2) {
			return 'Nom d\'au moins 2 caractères requis';
		}

		if (!userData.onboardingType || !['owner', 'member'].includes(userData.onboardingType)) {
			return 'Type d\'utilisateur requis (owner ou member)';
		}

		if (userData.phone && !/^[0-9+\-\s()]+$/.test(userData.phone)) {
			return 'Format de téléphone invalide';
		}

		if (userData.dateOfBirth) {
			const birthDate = new Date(userData.dateOfBirth);
			const now = new Date();
			const age = now.getFullYear() - birthDate.getFullYear();
			
			if (age < 13 || age > 120) {
				return 'L\'âge doit être entre 13 et 120 ans';
			}
		}

		return null;
	}

	/**
	 * Synchronise les données avec le store d'authentification
	 */
	private async syncAuthStore(user: User, accessToken: string, refreshToken: string): Promise<void> {
		// Le store d'authentification se met à jour automatiquement
		// Cette méthode peut être utilisée pour des synchronisations futures
		
		// Optionnel: déclencher des événements ou des callbacks
		console.log('Utilisateur connecté:', user.email);
	}

	/**
	 * Vérifie si l'utilisateur est connecté
	 */
	isAuthenticated(): boolean {
		const token = localStorage.getItem('auth-token');
		const refreshToken = localStorage.getItem('refresh-token');
		return !!(token && refreshToken);
	}

	/**
	 * Récupère l'utilisateur actuel depuis le localStorage
	 */
	getCurrentUser(): User | null {
		// Cette méthode pourrait être améliorée en décodant le JWT
		// Pour l'instant, on se fie au store d'authentification
		return auth.user;
	}

	/**
	 * Vérifie si l'utilisateur a un rôle spécifique
	 */
	hasRole(role: string): boolean {
		return auth.hasRole(role);
	}

	/**
	 * Vérifie si l'utilisateur est propriétaire
	 */
	isOwner(): boolean {
		return auth.isOwner();
	}

	/**
	 * Vérifie si l'utilisateur est membre
	 */
	isMember(): boolean {
		return auth.isMember();
	}
}

// Instance singleton du service d'authentification
export const authService = new AuthenticationService(); 
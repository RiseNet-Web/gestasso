import { browser } from '$app/environment';
import { apiService } from '$lib/services/api';
import type { 
	User, 
	LoginRequest, 
	RegisterRequest, 
	LoginResponse, 
	RegisterResponse, 
	RefreshTokenResponse,
	RefreshTokenRequest 
} from '$lib/types/entities';

export interface AuthStore {
	user: User | null;
	token: string | null;
	refreshToken: string | null;
	isLoading: boolean;
}

function createAuthStore() {
	let state = $state<AuthStore>({
		user: null,
		token: null,
		refreshToken: null,
		isLoading: false
	});

	const store = {
		get user() { return state.user; },
		get token() { return state.token; },
		get refreshToken() { return state.refreshToken; },
		get isLoading() { return state.isLoading; },

		/**
		 * Connexion utilisateur
		 */
		login: async (email: string, password: string) => {
			state.isLoading = true;

			try {
				const loginData: LoginRequest = { email, password };
				const response = await apiService.post<LoginResponse>('/login', loginData);

				if (response.success && response.data) {
					const { user, accessToken, refreshToken } = response.data;

					// Configurer le service API avec les tokens
					apiService.setTokens(accessToken, refreshToken);

					// Mettre à jour l'état
					state.user = user;
					state.token = accessToken;
					state.refreshToken = refreshToken;
					state.isLoading = false;

					return { success: true };
				} else {
					state.isLoading = false;
					return { 
						success: false, 
						error: response.error || 'Échec de la connexion',
						errors: response.errors 
					};
				}
			} catch (error) {
				state.isLoading = false;
				return { 
					success: false, 
					error: 'Erreur de connexion au serveur' 
				};
			}
		},

		/**
		 * Inscription utilisateur
		 */
		register: async (userData: RegisterRequest) => {
			state.isLoading = true;

			try {
				const response = await apiService.post<RegisterResponse>('/register', userData);

				state.isLoading = false;

				if (response.success && response.data) {
					const { user, accessToken, refreshToken } = response.data;

					// Configurer le service API avec les tokens
					apiService.setTokens(accessToken, refreshToken);

					// Mettre à jour l'état
					state.user = user;
					state.token = accessToken;
					state.refreshToken = refreshToken;

					return { success: true };
				} else {
					return { 
						success: false, 
						error: response.error || 'Échec de l\'inscription',
						errors: response.errors 
					};
				}
			} catch (error) {
				state.isLoading = false;
				return { 
					success: false, 
					error: 'Erreur de connexion au serveur' 
				};
			}
		},

		/**
		 * Déconnexion utilisateur
		 */
		logout: async () => {
			try {
				// Appeler l'API pour invalider le token côté serveur
				if (state.refreshToken) {
					await apiService.post('/logout', { 
						refreshToken: state.refreshToken 
					});
				}
			} catch (error) {
				console.warn('Erreur lors de la déconnexion:', error);
			}

			// Nettoyer l'état local et le service API
			apiService.clearTokens();

			state.user = null;
			state.token = null;
			state.refreshToken = null;
			state.isLoading = false;
		},

		/**
		 * Renouvellement du token d'authentification
		 * Note: Cette méthode est maintenant gérée automatiquement par le service API
		 */
		refreshAuthToken: async () => {
			if (!browser) return false;

			const refreshToken = localStorage.getItem('refresh-token');
			if (!refreshToken) return false;

			try {
				const refreshData: RefreshTokenRequest = { refreshToken };
				const response = await apiService.post<RefreshTokenResponse>('/refresh-token', refreshData);

				if (response.success && response.data) {
					const { accessToken, refreshToken: newRefreshToken, user } = response.data;

					// Configurer le service API avec les nouveaux tokens
					apiService.setTokens(accessToken, newRefreshToken);

					// Mettre à jour l'état
					state.user = user;
					state.token = accessToken;
					state.refreshToken = newRefreshToken;

					return true;
				}
			} catch (error) {
				console.warn('Erreur lors du refresh du token:', error);
			}

			return false;
		},

		/**
		 * Initialisation du store (vérification de l'authentification)
		 */
		initialize: async () => {
			if (!browser) return;

			state.isLoading = true;

			// Initialiser le service API avec les tokens du localStorage
			apiService.initializeFromStorage();

			const token = localStorage.getItem('auth-token');
			const refreshToken = localStorage.getItem('refresh-token');

			if (token && refreshToken) {
				try {
					// Vérifier la validité du token en récupérant le profil utilisateur
					const response = await apiService.get<User>('/profile');

					if (response.success && response.data) {
						state.user = response.data;
						state.token = token;
						state.refreshToken = refreshToken;
					} else {
						// Token invalide, nettoyer
						await store.logout();
					}
				} catch (error) {
					console.warn('Erreur lors de l\'initialisation:', error);
					await store.logout();
				}
			}

			state.isLoading = false;
		},

		/**
		 * Mise à jour du profil utilisateur
		 */
		updateProfile: async (profileData: Partial<User>) => {
			try {
				const response = await apiService.put<User>('/profile', profileData);

				if (response.success && response.data) {
					state.user = response.data;
					return { success: true };
				} else {
					return { 
						success: false, 
						error: response.error || 'Erreur lors de la mise à jour du profil',
						errors: response.errors 
					};
				}
			} catch (error) {
				return { 
					success: false, 
					error: 'Erreur de connexion au serveur' 
				};
			}
		},

		/**
		 * Vérifie si l'utilisateur a un rôle spécifique
		 */
		hasRole: (role: string): boolean => {
			return state.user?.roles?.includes(role) || false;
		},

		/**
		 * Vérifie si l'utilisateur est un propriétaire de club
		 */
		isOwner: (): boolean => {
			return store.hasRole('ROLE_CLUB_OWNER') || state.user?.onboardingType === 'owner';
		},

		/**
		 * Vérifie si l'utilisateur est un membre
		 */
		isMember: (): boolean => {
			return store.hasRole('ROLE_USER') || state.user?.onboardingType === 'member';
		},

		/**
		 * Synchronise l'état avec les tokens du service API
		 * Utile après un refresh automatique
		 */
		syncWithApiService: () => {
			if (browser) {
				const token = localStorage.getItem('auth-token');
				const refreshToken = localStorage.getItem('refresh-token');
				
				if (token && refreshToken) {
					state.token = token;
					state.refreshToken = refreshToken;
				}
			}
		}
	};

	return store;
}

export const auth = createAuthStore(); 
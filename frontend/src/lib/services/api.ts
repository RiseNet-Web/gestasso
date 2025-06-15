import { API_BASE_URL } from '$lib/config/env';
import type { ApiError } from '$lib/types/entities';

export interface ApiResponse<T> {
	success: boolean;
	data?: T;
	error?: string;
	errors?: string[];
}

class ApiService {
	private baseUrl: string;
	private token: string | null = null;
	private refreshToken: string | null = null;
	private isRefreshing = false;
	private failedQueue: Array<{
		resolve: (value: any) => void;
		reject: (reason: any) => void;
	}> = [];

	constructor() {
		this.baseUrl = API_BASE_URL;
	}

	/**
	 * Définit les tokens d'authentification
	 */
	setTokens(accessToken: string | null, refreshToken: string | null): void {
		this.token = accessToken;
		this.refreshToken = refreshToken;
	}

	/**
	 * Définit le token d'authentification pour les requêtes
	 */
	setToken(token: string | null): void {
		this.token = token;
	}

	/**
	 * Définit le refresh token
	 */
	setRefreshToken(refreshToken: string | null): void {
		this.refreshToken = refreshToken;
	}

	/**
	 * Traite la queue des requêtes en attente après refresh
	 */
	private processQueue(error: any, token: string | null = null) {
		this.failedQueue.forEach(({ resolve, reject }) => {
			if (error) {
				reject(error);
			} else {
				resolve(token);
			}
		});
		
		this.failedQueue = [];
	}

	/**
	 * Refresh automatique du token
	 */
	private async refreshAccessToken(): Promise<string | null> {
		if (!this.refreshToken) {
			throw new Error('No refresh token available');
		}

		try {
			const response = await fetch(`${this.baseUrl}/refresh-token`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'application/json'
				},
				body: JSON.stringify({ refreshToken: this.refreshToken }),
				credentials: 'include'
			});

			if (!response.ok) {
				throw new Error('Token refresh failed');
			}

			const data = await response.json();
			
			if (data.accessToken && data.refreshToken) {
				// Mettre à jour les tokens
				this.token = data.accessToken;
				this.refreshToken = data.refreshToken;

				// Sauvegarder dans localStorage
				if (typeof window !== 'undefined') {
					localStorage.setItem('auth-token', data.accessToken);
					localStorage.setItem('refresh-token', data.refreshToken);
				}

				return data.accessToken;
			}

			throw new Error('Invalid refresh response');
		} catch (error) {
			// Nettoyer les tokens invalides
			this.token = null;
			this.refreshToken = null;
			
			if (typeof window !== 'undefined') {
				localStorage.removeItem('auth-token');
				localStorage.removeItem('refresh-token');
			}

			throw error;
		}
	}

	/**
	 * Effectue une requête HTTP avec gestion automatique du refresh
	 */
	private async request<T>(
		endpoint: string,
		options: RequestInit = {}
	): Promise<ApiResponse<T>> {
		const url = `${this.baseUrl}${endpoint}`;
		
		const headers: Record<string, string> = {
			'Content-Type': 'application/json',
			'Accept': 'application/json',
			...((options.headers as Record<string, string>) || {})
		};

		// Ajouter le token d'authentification si disponible
		if (this.token) {
			headers['Authorization'] = `Bearer ${this.token}`;
		}

		try {
			const response = await fetch(url, {
				...options,
				headers,
				credentials: 'include'
			});

			// Gestion du token expiré (401)
			if (response.status === 401 && this.token && this.refreshToken) {
				// Si un refresh est déjà en cours, attendre
				if (this.isRefreshing) {
					return new Promise((resolve, reject) => {
						this.failedQueue.push({ resolve, reject });
					}).then(() => {
						// Retry la requête avec le nouveau token
						return this.request<T>(endpoint, options);
					});
				}

				this.isRefreshing = true;

				try {
					const newToken = await this.refreshAccessToken();
					this.processQueue(null, newToken);
					this.isRefreshing = false;

					// Retry la requête avec le nouveau token
					return this.request<T>(endpoint, options);
				} catch (refreshError) {
					this.processQueue(refreshError, null);
					this.isRefreshing = false;

					// Rediriger vers la page de connexion
					if (typeof window !== 'undefined') {
						window.location.href = '/login';
					}

					return {
						success: false,
						error: 'Session expirée, veuillez vous reconnecter'
					};
				}
			}

			const contentType = response.headers.get('Content-Type') || '';
			let data: any = null;

			// Parser la réponse selon le type de contenu
			if (contentType.includes('application/json')) {
				data = await response.json();
			} else {
				data = await response.text();
			}

			if (!response.ok) {
				// Gestion des erreurs de l'API
				const errorMessage = data?.error || data?.message || `Erreur HTTP ${response.status}`;
				const errors = data?.errors || [];
				
				return {
					success: false,
					error: errorMessage,
					errors
				};
			}

			return {
				success: true,
				data
			};

		} catch (error) {
			console.error('Erreur de requête API:', error);
			return {
				success: false,
				error: error instanceof Error ? error.message : 'Erreur de connexion au serveur'
			};
		}
	}

	/**
	 * Requête GET
	 */
	async get<T>(endpoint: string): Promise<ApiResponse<T>> {
		return this.request<T>(endpoint, { method: 'GET' });
	}

	/**
	 * Requête POST
	 */
	async post<T>(endpoint: string, data?: any): Promise<ApiResponse<T>> {
		return this.request<T>(endpoint, {
			method: 'POST',
			body: data ? JSON.stringify(data) : undefined
		});
	}

	/**
	 * Requête PUT
	 */
	async put<T>(endpoint: string, data?: any): Promise<ApiResponse<T>> {
		return this.request<T>(endpoint, {
			method: 'PUT',
			body: data ? JSON.stringify(data) : undefined
		});
	}

	/**
	 * Requête DELETE
	 */
	async delete<T>(endpoint: string): Promise<ApiResponse<T>> {
		return this.request<T>(endpoint, { method: 'DELETE' });
	}

	/**
	 * Upload de fichier
	 */
	async upload<T>(endpoint: string, formData: FormData): Promise<ApiResponse<T>> {
		const url = `${this.baseUrl}${endpoint}`;
		
		const headers: Record<string, string> = {};
		
		// Ajouter le token d'authentification si disponible
		if (this.token) {
			headers['Authorization'] = `Bearer ${this.token}`;
		}

		try {
			const response = await fetch(url, {
				method: 'POST',
				headers,
				body: formData,
				credentials: 'include'
			});

			// Gestion du token expiré pour les uploads aussi
			if (response.status === 401 && this.token && this.refreshToken) {
				if (this.isRefreshing) {
					return new Promise((resolve, reject) => {
						this.failedQueue.push({ resolve, reject });
					}).then(() => {
						return this.upload<T>(endpoint, formData);
					});
				}

				this.isRefreshing = true;

				try {
					const newToken = await this.refreshAccessToken();
					this.processQueue(null, newToken);
					this.isRefreshing = false;
					return this.upload<T>(endpoint, formData);
				} catch (refreshError) {
					this.processQueue(refreshError, null);
					this.isRefreshing = false;
					
					if (typeof window !== 'undefined') {
						window.location.href = '/login';
					}

					return {
						success: false,
						error: 'Session expirée, veuillez vous reconnecter'
					};
				}
			}

			const data = await response.json();

			if (!response.ok) {
				return {
					success: false,
					error: data?.error || `Erreur HTTP ${response.status}`,
					errors: data?.errors || []
				};
			}

			return {
				success: true,
				data
			};

		} catch (error) {
			console.error('Erreur d\'upload:', error);
			return {
				success: false,
				error: error instanceof Error ? error.message : 'Erreur de connexion au serveur'
			};
		}
	}

	/**
	 * Initialise les tokens depuis le localStorage
	 */
	initializeFromStorage(): void {
		if (typeof window !== 'undefined') {
			const token = localStorage.getItem('auth-token');
			const refreshToken = localStorage.getItem('refresh-token');
			
			if (token && refreshToken) {
				this.setTokens(token, refreshToken);
			}
		}
	}

	/**
	 * Nettoie tous les tokens
	 */
	clearTokens(): void {
		this.token = null;
		this.refreshToken = null;
		
		if (typeof window !== 'undefined') {
			localStorage.removeItem('auth-token');
			localStorage.removeItem('refresh-token');
		}
	}
}

// Instance singleton du service API
export const apiService = new ApiService(); 
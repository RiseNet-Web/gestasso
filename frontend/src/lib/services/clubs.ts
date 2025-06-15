import { apiService } from './api';
import type { 
	Club, 
	Team, 
	ClubSearchParams, 
	PaginatedResponse, 
	CreateClubRequest, 
	JoinTeamRequest 
} from '$lib/types/entities';

export class ClubService {
	/**
	 * Rechercher des clubs avec pagination et filtres
	 */
	async searchClubs(params: ClubSearchParams = {}) {
		const searchParams = new URLSearchParams();
		
		if (params.search) searchParams.append('search', params.search);
		if (params.sport) searchParams.append('sport', params.sport);
		if (params.location) searchParams.append('location', params.location);
		if (params.page) searchParams.append('page', params.page.toString());
		if (params.limit) searchParams.append('limit', params.limit.toString());
		
		const queryString = searchParams.toString();
		const endpoint = `/clubs/search${queryString ? `?${queryString}` : ''}`;
		
		return apiService.get<PaginatedResponse<Club>>(endpoint);
	}

	/**
	 * Récupérer un club par son ID avec ses équipes
	 */
	async getClubById(clubId: string) {
		return apiService.get<Club>(`/clubs/${clubId}`);
	}

	/**
	 * Récupérer les équipes d'un club
	 */
	async getClubTeams(clubId: string) {
		return apiService.get<Team[]>(`/clubs/${clubId}/teams`);
	}

	/**
	 * Créer un nouveau club
	 */
	async createClub(clubData: CreateClubRequest) {
		// Si un logo est fourni, l'uploader séparément
		if (clubData.logo) {
			const formData = new FormData();
			formData.append('logo', clubData.logo);
			
			// Upload du logo
			const logoResponse = await apiService.uploadFile<{ url: string }>('/upload/club-logo', formData);
			if (!logoResponse.success) {
				return { success: false, error: 'Erreur lors de l\'upload du logo' };
			}
			
			// Créer le club avec l'URL du logo
			const { logo, ...clubDataWithoutLogo } = clubData;
			return apiService.post<Club>('/clubs', {
				...clubDataWithoutLogo,
				image: logoResponse.data?.url
			});
		}
		
		// Créer le club sans logo
		const { logo, ...clubDataWithoutLogo } = clubData;
		return apiService.post<Club>('/clubs', clubDataWithoutLogo);
	}

	/**
	 * Mettre à jour un club
	 */
	async updateClub(clubId: string, clubData: Partial<CreateClubRequest>) {
		if (clubData.logo) {
			const formData = new FormData();
			formData.append('logo', clubData.logo);
			
			// Upload du nouveau logo
			const logoResponse = await apiService.uploadFile<{ url: string }>('/upload/club-logo', formData);
			if (!logoResponse.success) {
				return { success: false, error: 'Erreur lors de l\'upload du logo' };
			}
			
			// Mettre à jour le club avec la nouvelle URL
			const { logo, ...clubDataWithoutLogo } = clubData;
			return apiService.put<Club>(`/clubs/${clubId}`, {
				...clubDataWithoutLogo,
				image: logoResponse.data?.url
			});
		}
		
		const { logo, ...clubDataWithoutLogo } = clubData;
		return apiService.put<Club>(`/clubs/${clubId}`, clubDataWithoutLogo);
	}

	/**
	 * Supprimer un club
	 */
	async deleteClub(clubId: string) {
		return apiService.delete(`/clubs/${clubId}`);
	}

	/**
	 * Faire une demande pour rejoindre une équipe
	 */
	async requestToJoinTeam(clubId: string, teamId: string, requestData: JoinTeamRequest) {
		return apiService.post(`/clubs/${clubId}/teams/${teamId}/join`, requestData);
	}

	/**
	 * Récupérer les sports disponibles
	 */
	async getSports() {
		return apiService.get<string[]>('/sports');
	}

	/**
	 * Récupérer les localisations disponibles
	 */
	async getLocations() {
		return apiService.get<string[]>('/locations');
	}

	/**
	 * Récupérer les clubs de l'utilisateur connecté
	 */
	async getUserClubs() {
		return apiService.get<Club[]>('/user/clubs');
	}
}

export const clubService = new ClubService(); 
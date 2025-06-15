export enum UserRole {
	MEMBER = 'member',
	OWNER = 'owner'
}

export interface User {
	id: number;
	email: string;
	firstName: string;
	lastName: string;
	phone?: string;
	dateOfBirth?: string;
	roles: string[];
	onboardingType: 'owner' | 'member';
	onboardingCompleted: boolean;
	isActive: boolean;
	createdAt: string;
	updatedAt: string;
}

export interface Team {
	id: string;
	name: string;
	category: string;
	level: string;
	membersCount: number;
	clubId: string;
	createdAt: string;
	updatedAt: string;
}

export interface Club {
	id: string;
	name: string;
	sport: string;
	description: string;
	location: string;
	image?: string;
	teamsCount: number;
	membersCount: number;
	teams: Team[];
	isPublic: boolean;
	allowRegistrations: boolean;
	ownerId: string;
	createdAt: string;
	updatedAt: string;
}

export interface AuthTokens {
	accessToken: string;
	refreshToken: string;
}

export interface LoginRequest {
	email: string;
	password: string;
}

export interface RegisterRequest {
	email: string;
	password: string;
	firstName: string;
	lastName: string;
	onboardingType: 'owner' | 'member';
	phone?: string;
	dateOfBirth?: string;
}

export interface LoginResponse {
	accessToken: string;
	refreshToken: string;
	user: User;
}

export interface RegisterResponse {
	accessToken: string;
	refreshToken: string;
	user: User;
}

export interface RefreshTokenRequest {
	refreshToken: string;
}

export interface RefreshTokenResponse {
	accessToken: string;
	refreshToken: string;
	user: User;
}

export interface ApiError {
	error: string;
	errors?: string[];
}

export interface CreateClubRequest {
	name: string;
	sport: string;
	description: string;
	location: string;
	isPublic: boolean;
	allowRegistrations: boolean;
	logo?: File;
}

export interface JoinTeamRequest {
	motivation: string;
	experience: string;
	position?: string;
	age: number;
	availability: string;
}

export interface ClubSearchParams {
	search?: string;
	sport?: string;
	location?: string;
	page?: number;
	limit?: number;
}

export interface PaginatedResponse<T> {
	data: T[];
	total: number;
	page: number;
	limit: number;
	totalPages: number;
} 
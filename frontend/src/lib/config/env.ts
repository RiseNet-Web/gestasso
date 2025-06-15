import { dev } from '$app/environment';

// Configuration des variables d'environnement
export const env = {
	API_URL: import.meta.env.VITE_API_URL || 'http://localhost:8000',
	API_BASE_PATH: import.meta.env.VITE_API_BASE_PATH || '/api',
	NODE_ENV: import.meta.env.VITE_NODE_ENV || (dev ? 'development' : 'production'),
	COOKIE_SECURE: import.meta.env.VITE_COOKIE_SECURE === 'true' || !dev,
	COOKIE_DOMAIN: import.meta.env.VITE_COOKIE_DOMAIN || 'localhost'
} as const;

// URL complète de l'API
export const API_BASE_URL = `${env.API_URL}${env.API_BASE_PATH}`;

// Configuration des cookies
export const COOKIE_CONFIG = {
	secure: env.COOKIE_SECURE,
	domain: env.COOKIE_DOMAIN,
	sameSite: 'lax' as const,
	path: '/'
} as const; 
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function urlIsActive(
    urlToCheck: string,
    currentUrl: string,
) {
    return urlToCheck === currentUrl;
}

export function toUrl(href: string) {
    return href;
}

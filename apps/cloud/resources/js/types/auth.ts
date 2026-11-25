export type User = {
    id: number;
    name: string;
    email: string | null;
    login: string;
    avatar?: string | null;
    isAdmin?: boolean;
    hasAccess?: boolean;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

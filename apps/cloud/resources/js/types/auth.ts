export type User = {
    id: number;
    name: string;
    email: string | null;
    login: string;
    avatar?: string | null;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Severity = 'info' | 'warn' | 'danger';

export type AnalysisSummary = {
    id: number;
    repository: string;
    repositoryId: number;
    prNumber: number | null;
    prUrl: string | null;
    headSha: string;
    severity: Severity | null;
    statementCount: number;
    dangerCount: number;
    warnCount: number;
    migrations: string[];
    createdAt: string | null;
};

export type RepositorySummary = {
    id: number;
    fullName: string;
    owner: string;
    name: string;
    private: boolean;
    url: string;
    analysesCount: number | null;
    latest: {
        id: number;
        severity: Severity | null;
        prNumber: number | null;
        createdAt: string | null;
    } | null;
};

export type InstallationSummary = {
    id: number;
    login: string;
    type: 'User' | 'Organization';
    avatar: string | null;
    repositories: number;
    suspended: boolean;
    settingsUrl: string;
};

export type Finding = {
    rule: string;
    table: string | null;
    operation: string;
    algorithm: string | null;
    lock: string | null;
    cost: string | null;
    rebuilds_table: boolean;
    blocks_writes: boolean;
    severity: Severity;
    message: string;
    suggestion: string | null;
};

export type Advisory = {
    id: string;
    severity: Severity;
    table: string | null;
    message: string;
};

export type Statement = {
    index: number;
    migration: string | null;
    sql: string;
    table: string | null;
    severity: Severity;
    algorithm: string | null;
    lock: string | null;
    rebuilds_table: boolean;
    blocks_writes: boolean;
    on_new_table: boolean;
    estimate: { label: string; basis: string } | null;
    findings: Finding[];
    advisories: Advisory[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type StreamBatchRow = {
    protocol: 'tcp' | 'udp';
    port: number;
    origin: string;
    originPort: number;
    status: 'pending' | 'done' | 'failed';
    error?: string;
};
export function parseStreamBatch(source: string): StreamBatchRow[] {
    const lines = source
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);

    if (!lines.length) {
        throw new Error('请填写转发记录');
    }

    if (lines.length > 10) {
        throw new Error('单次最多 10 条');
    }

    const seen = new Set<string>();

    return lines.map((line, index) => {
        const parts = line.split('|').map((part) => part.trim());
        const [protocol, port, origin, originPort] = parts;

        if (
            parts.length !== 4 ||
            !['tcp', 'udp'].includes(protocol) ||
            !origin ||
            ![port, originPort].every(
                (value) =>
                    /^\d+$/.test(value) &&
                    Number(value) >= 1 &&
                    Number(value) <= 65535,
            )
        ) {
            throw new Error(
                `第 ${index + 1} 行格式有误，请按“协议 | 监听端口 | 源站 | 源站端口”填写`,
            );
        }

        const key = `${protocol}:${port}`;

        if (seen.has(key)) {
            throw new Error(`第 ${index + 1} 行监听协议和端口重复`);
        }

        seen.add(key);

        return {
            protocol: protocol as 'tcp' | 'udp',
            port: Number(port),
            origin,
            originPort: Number(originPort),
            status: 'pending',
        };
    });
}

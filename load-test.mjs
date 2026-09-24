const BASE_URL =
    'https://aurapay-production-vy9b77.laravel.cloud';

const URL =
    `${BASE_URL}/dashboard`;

const cookie =
    process.env.AURAPAY_COOKIE;

const concurrency =
    Number(process.env.CONCURRENCY ?? 5);

const durationSeconds =
    Number(process.env.DURATION ?? 30);

if (!cookie) {
    console.error(
        'ERROR: AURAPAY_COOKIE is not defined.'
    );

    process.exit(1);
}

const endTime =
    Date.now() + durationSeconds * 1000;

let successful = 0;
let failed = 0;

const latencies = [];
const statuses = {};

async function worker() {
    while (Date.now() < endTime) {
        const started =
            performance.now();

        try {
            const response =
                await fetch(URL, {
                    method: 'GET',

                    headers: {
                        Accept: 'text/html',
                        Cookie: cookie,
                    },

                    redirect: 'manual',
                });

            await response.arrayBuffer();

            const elapsed =
                performance.now() - started;

            latencies.push(elapsed);

            statuses[response.status] =
                (statuses[response.status] ?? 0) + 1;

            if (response.status === 200) {
                successful++;
            } else {
                failed++;
            }

        } catch (error) {
            failed++;

            latencies.push(
                performance.now() - started
            );
        }
    }
}

await Promise.all(
    Array.from(
        { length: concurrency },
        () => worker()
    )
);

latencies.sort(
    (a, b) => a - b
);

function percentile(p) {
    if (latencies.length === 0) {
        return 0;
    }

    const index =
        Math.min(
            latencies.length - 1,
            Math.ceil(
                p * latencies.length
            ) - 1
        );

    return latencies[index];
}

const total =
    successful + failed;

const requestsPerSecond =
    total / durationSeconds;

const errorRate =
    total === 0
        ? 0
        : failed / total * 100;

console.log('');
console.log(
    '========================================'
);

console.log(
    '          AURAPAY LOAD TEST'
);

console.log(
    '========================================'
);

console.log(`URL:          ${URL}`);
console.log(`Concurrency:  ${concurrency}`);
console.log(`Duration:     ${durationSeconds} s`);

console.log('');
console.log(`Requests:     ${total}`);
console.log(`Successful:   ${successful}`);
console.log(`Failed:       ${failed}`);

console.log(
    `Throughput:   ${requestsPerSecond.toFixed(2)} req/s`
);

console.log(
    `Error rate:   ${errorRate.toFixed(2)} %`
);

console.log('');
console.log('Response time');

console.log(
    `p50:          ${percentile(0.50).toFixed(2)} ms`
);

console.log(
    `p95:          ${percentile(0.95).toFixed(2)} ms`
);

console.log(
    `p99:          ${percentile(0.99).toFixed(2)} ms`
);

console.log('');

console.log(
    'HTTP status:',
    statuses
);

console.log(
    '========================================'
);
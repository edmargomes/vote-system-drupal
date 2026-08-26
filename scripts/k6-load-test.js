import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

const BASE_URL      = __ENV.BASE_URL      || 'http://voting-system.lndo.site';
const QUESTION_UUID = __ENV.QUESTION_UUID;
const OPTION_UUID   = __ENV.OPTION_UUID;

export const options = {
  stages: [
    // Smoke — catches mis-configuration early at near-zero cost.
    { duration: '10s', target: 1    },

    // Ramp-up.
    { duration: '1m',  target: 200  },

    // Hold at medium load.
    { duration: '2m',  target: 1000 },

    // Spike — 2000 VUs hit the vote endpoint concurrently.
    { duration: '1m',  target: 2000 },

    // Ramp-down.
    { duration: '1m',  target: 0    },
  ],
  thresholds: {
    // 95th-percentile response time under 500 ms across all requests.
    http_req_duration: ['p(95)<500'],
    // Custom error rate under 1% (excludes 409, which is correct behaviour).
    errors: ['rate<0.01'],
  },
};

export function setup() {
  if (!QUESTION_UUID || !OPTION_UUID) {
    throw new Error(
      'QUESTION_UUID and OPTION_UUID env vars are required. ' +
      'Run: lando drush sql:query to find them.'
    );
  }
}

export default function () {
  // Each VU maps to a unique pre-created Drupal user so the DB unique
  // constraint (user_id, question_id) is only triggered if the same VU
  // iterates more than once — which is the correct duplicate-vote case.
  const username    = `loadtest_user_${__VU}`;
  const password    = 'loadtest_pass';
  const credentials = btoa(`${username}:${password}`);

  const headers = {
    'Authorization': `Basic ${credentials}`,
    'Content-Type':  'application/json',
  };

  // Read path — confirms the listing endpoint handles concurrent load.
  const listRes = http.get(`${BASE_URL}/api/v1/questions`, { headers });
  check(listRes, { 'questions list 200': (r) => r.status === 200 });
  errorRate.add(listRes.status !== 200);

  // Write path — the concurrency-sensitive endpoint.
  // 200 on first attempt; 409 on repeat iterations within the same VU.
  // Both outcomes are correct; only 4xx/5xx other than 409 are errors.
  const voteRes = http.post(
    `${BASE_URL}/api/v1/questions/${QUESTION_UUID}/vote`,
    JSON.stringify({ option_uuid: OPTION_UUID }),
    { headers }
  );
  const voteOk = voteRes.status === 200 || voteRes.status === 409;
  check(voteRes, { 'vote accepted or already cast': () => voteOk });
  errorRate.add(!voteOk);

  sleep(1);
}

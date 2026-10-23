-- A delegated login binding is spent once (072): the pre-session cookie that carries it is stateless.
-- Federated identity links live in the access model (user-federated-identity, migration 20261023095150).
ALTER TABLE _oauth_replay_jti MODIFY purpose ENUM('client_assertion', 'request_object', 'dpop', 'jwt_bearer', 'authorization_request', 'delegated_login') NOT NULL;

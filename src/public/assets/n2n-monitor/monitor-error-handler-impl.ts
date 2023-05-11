export class MonitorErrorHandlerImpl {
	private monitorUrl: URL|undefined;

	constructor(url: URL) {
		this.monitorUrl = url;
	}

	handleError(error: Error) {
		const options = {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: this.errorToBodyJson(error)
		};

		if (this.monitorUrl === undefined) {
			console.error("monitorUrl is undefined");
			return;
		}

		fetch(this.monitorUrl, options).catch(error => console.error(error));
		console.error(error);
	}

	private errorToBodyJson(error: Error) {
		let errorStack = error.stack;
		if (!errorStack) {
			errorStack = "";
		}

		const regex = /(https?:\/\/[^\s]+):(\d+):(\d+)/;
		const match = regex.exec(errorStack);

		let fileNameLineAndColumn = null;
		if (match !== null) {
			fileNameLineAndColumn = match[1] + match[2] + match[3];
		}

		return JSON.stringify({
			discriminator: (error.name + fileNameLineAndColumn).replace(/\s/g, ""),
			severity: 'high',
			name: error.name,
			message: error.message,
			stackTrace: error.stack
		});
	}
}